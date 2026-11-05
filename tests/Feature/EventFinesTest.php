<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Fine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Each event fines absent and late students its own amounts; paying every fine clears the student.
 */
class EventFinesTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->officer = User::role('officer')->first();
        $this->student = User::role('student')->first();
    }

    private function student(string $id): User
    {
        $user = User::create(['name' => 'Student ' . $id, 'username' => $id, 'password' => 'x', 'department_id' => $this->student->department_id]);
        $user->assignRole('student');

        return $user;
    }

    private function endedEvent(array $attrs = []): Event
    {
        return Event::create($attrs + [
            'department_id' => $this->student->department_id,
            'name' => 'Assembly',
            'start_time' => now()->subHours(2),
            'end_time' => now()->subHour(),
            'absent_fine' => 50,
            'late_fine' => 20,
        ]);
    }

    public function test_officer_sets_absent_and_late_fines_on_an_event(): void
    {
        $this->actingAs($this->officer)->post(route('events.store'), [
            'name' => 'General assembly',
            'date' => now()->addDay()->toDateString(),
            'sessions' => ['morning' => ['enabled' => '1', 'start' => '08:00', 'end' => '10:00']],
            'absent_fine' => '50',
            'late_fine' => '20.50',
            'missed_in_fine' => '0',
            'missed_out_fine' => '0',
        ])->assertSessionHasNoErrors()->assertRedirect(route('events.index'));

        $event = Event::sole();
        $this->assertSame('50.00', $event->absent_fine);
        $this->assertSame('20.50', $event->late_fine);

        $this->post(route('events.store'), ['name' => 'Bad', 'date' => now()->toDateString(), 'sessions' => ['morning' => ['enabled' => '1', 'start' => '08:00', 'end' => '10:00']],
            'absent_fine' => -5, 'late_fine' => 0, 'missed_in_fine' => 0, 'missed_out_fine' => 0])
            ->assertSessionHasErrors('absent_fine');
    }

    public function test_processing_fines_absent_and_late_students_but_not_present_ones(): void
    {
        $event = $this->endedEvent();
        $late = $this->student('LGC25-200001');
        $present = $this->student('LGC25-200002');
        $session = $event->sessions->first();
        Attendance::create(['event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $late->id, 'status' => 'late', 'time_in_at' => now(), 'time_out_at' => now()]);
        Attendance::create(['event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $present->id, 'status' => 'present', 'time_in_at' => now(), 'time_out_at' => now()]);

        $this->actingAs($this->officer)->post(route('events.processFines', $event))
            ->assertSessionHas('status', fn ($s) => str_contains($s, '1 absent, 1 late'));

        $this->assertEquals(50, Fine::where('user_id', $this->student->id)->sole()->amount);
        $this->assertStringStartsWith('Absent (', Fine::where('user_id', $this->student->id)->sole()->reason);
        $this->assertEquals(20, Fine::where('user_id', $late->id)->sole()->amount);
        $this->assertSame(0, Fine::where('user_id', $present->id)->count());
        $this->assertSame('late', Attendance::where('user_id', $late->id)->value('status')); // late stays late

        // Processing twice never double-charges
        $this->post(route('events.processFines', $event))->assertSessionHas('error');
        $this->assertSame(2, Fine::count());
    }

    public function test_zero_amount_means_no_fine(): void
    {
        $this->endedEvent(['absent_fine' => 0])->markAbsentees();

        $this->assertSame('absent', Attendance::where('user_id', $this->student->id)->value('status'));
        $this->assertSame(0, Fine::count());
    }

    public function test_student_is_cleared_once_every_fine_is_paid(): void
    {
        $this->endedEvent()->markAbsentees();
        $this->endedEvent(['name' => 'Seminar'])->markAbsentees();

        $this->assertFalse($this->student->isCleared());
        $this->actingAs($this->officer)->get(route('clearance.index'))->assertSee('₱100.00')->assertSee('Not cleared');

        Fine::all()->each(fn ($fine) => $this->post(route('fines.markAsPaid', $fine)));

        $this->assertTrue($this->student->fresh()->isCleared());
        $this->get(route('clearance.index'))->assertSee('1 of 1 students cleared');
    }

    public function test_scheduled_processing_charges_fines(): void
    {
        $this->endedEvent();

        $this->artisan('cleartrack:process-fines')->assertSuccessful();

        $this->assertSame(1, Fine::where('user_id', $this->student->id)->count());
    }
}
