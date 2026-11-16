<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Event;
use App\Models\Fine;
use App\Models\FineExemption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Officers excuse students from one event's fines, before or after the fines are charged.
 */
class FineExemptionTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;
    private User $student;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $this->officer = User::role('officer')->first();
        $this->student = User::role('student')->first();
        $this->other = User::create(['name' => 'Reyes, Ben', 'username' => 'LGC24-6001', 'password' => 'x', 'department_id' => $this->officer->department_id]);
        $this->other->assignRole('student');
    }

    private function endedEvent(string $name = 'Nurses Week, Day 1'): Event
    {
        return Event::create(['department_id' => $this->officer->department_id, 'name' => $name, 'start_time' => now()->subHours(3),
            'end_time' => now()->subHours(2), 'missed_in_fine' => 50, 'missed_out_fine' => 50]);
    }

    private function exempt(Event $event, User $student, string $reason = 'Excuse letter')
    {
        return $this->actingAs($this->officer)->post(route('exemptions.store'), ['event_id' => $event->id, 'student_id' => strtolower($student->username), 'reason' => $reason]);
    }

    public function test_exempted_before_processing_gets_no_fines(): void
    {
        $event = $this->endedEvent();
        $this->exempt($event, $this->student, 'Event performer')->assertSessionHasNoErrors()
            ->assertSessionHas('status', "{$this->student->name} is exempted from {$event->name}.");

        $event->markAbsentees();

        $this->assertSame(0, Fine::where('user_id', $this->student->id)->count());
        $this->assertSame('absent', Attendance::where('user_id', $this->student->id)->value('status')); // attendance stays truthful
        $this->assertEquals(100, Fine::where('user_id', $this->other->id)->sole()->amount);

        $this->get(route('exemptions.index'))->assertOk()->assertSee('Event performer')->assertSee($this->student->username);
        $this->get(route('events.show', $event))->assertSee('Exempted');
        $this->assertSame('Exempted a student from an event', ActivityLog::where('action', 'like', 'Exempted%')->value('action'));

        // An admin Fix and charge again doesn't charge them either
        $event->reprocess();
        $this->assertSame(0, Fine::where('user_id', $this->student->id)->count());
    }

    public function test_exempting_after_processing_marks_fines_exempted_and_removing_restores_them(): void
    {
        $event = $this->endedEvent();
        $event->markAbsentees();
        $later = $this->endedEvent('Day 2');
        Fine::create(['user_id' => $this->student->id, 'event_id' => $later->id, 'amount' => 20, 'status' => 'unpaid']);

        $this->exempt($event, $this->student)
            ->assertSessionHas('status', fn ($s) => str_contains($s, '1 fine (₱100.00) marked exempted'));

        $this->assertSame('exempted', Fine::where('event_id', $event->id)->where('user_id', $this->student->id)->value('status'));
        $this->assertSame('unpaid', Fine::where('event_id', $later->id)->value('status')); // other events untouched
        $this->assertSame('unpaid', Fine::where('event_id', $event->id)->where('user_id', $this->other->id)->value('status'));

        Fine::where('event_id', $later->id)->update(['status' => 'paid']);
        $this->assertTrue($this->student->fresh()->isCleared()); // exempted doesn't block clearance
        $this->get(route('fines.index', ['status' => 'exempted']))->assertSee($this->student->name)->assertDontSee('Reyes, Ben');

        $this->delete(route('exemptions.destroy', FineExemption::sole()))
            ->assertSessionHas('status', fn ($s) => str_contains($s, '1 fine is unpaid again'));
        $this->assertSame('unpaid', Fine::where('event_id', $event->id)->where('user_id', $this->student->id)->value('status'));
        $this->assertFalse($this->student->fresh()->isCleared());
    }

    public function test_all_events_exempts_from_every_existing_event(): void
    {
        $day1 = $this->endedEvent();
        $day1->markAbsentees(); // charged: ₱100 each
        $day2 = $this->endedEvent('Day 2');
        $this->exempt($day2, $this->student); // already exempted from Day 2: skipped, not doubled
        $otherDept = Event::create(['department_id' => Department::where('id', '!=', $this->officer->department_id)->value('id'), 'name' => 'Theirs', 'start_time' => now()->subHours(3), 'end_time' => now()->subHours(2)]);

        $this->actingAs($this->officer)->post(route('exemptions.store'), ['event_id' => 'all', 'student_id' => $this->student->username, 'reason' => 'Medical'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', "{$this->student->name} is exempted from {$day1->name}. 1 fine (₱100.00) marked exempted.");

        $this->assertSame([$day1->id, $day2->id], FineExemption::where('user_id', $this->student->id)->orderBy('event_id')->pluck('event_id')->all());
        $this->assertFalse($otherDept->exemptions()->exists());
        $this->assertSame('exempted', Fine::where('user_id', $this->student->id)->value('status'));

        $this->actingAs($this->officer)->post(route('exemptions.store'), ['event_id' => 'all', 'student_id' => $this->student->username, 'reason' => 'Medical'])
            ->assertSessionHasErrors('student_id'); // nothing left to exempt
        $this->post(route('exemptions.store'), ['event_id' => 'nonsense', 'student_id' => $this->student->username, 'reason' => 'x'])->assertSessionHasErrors('event_id');
    }

    public function test_reason_required_no_duplicates_and_own_department_only(): void
    {
        $event = $this->endedEvent();

        $this->exempt($event, $this->student, '')->assertSessionHasErrors('reason');
        $this->exempt($event, $this->student)->assertSessionHasNoErrors();
        $this->exempt($event, $this->student)->assertSessionHasErrors('student_id');
        $this->actingAs($this->officer)->post(route('exemptions.store'), ['event_id' => $event->id, 'student_id' => 'LGC00-0000', 'reason' => 'x'])
            ->assertSessionHasErrors('student_id');

        $otherDept = Department::where('id', '!=', $this->officer->department_id)->value('id');
        $theirs = Event::create(['department_id' => $otherDept, 'name' => 'Theirs', 'start_time' => now()->subHours(3), 'end_time' => now()->subHours(2)]);
        $this->exempt($theirs, $this->other)->assertForbidden();
        $outsider = User::create(['name' => 'Out, Sider', 'username' => 'LGC24-6002', 'password' => 'x', 'department_id' => $otherDept]);
        $outsider->assignRole('student');
        $this->exempt($event, $outsider)->assertSessionHasErrors('student_id');

        $this->actingAs($this->student)->get(route('exemptions.index'))->assertForbidden();
    }
}
