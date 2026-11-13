<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\Fine;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin fixes a processed event (e.g. time out was never really required) and it's charged again.
 */
class AdminEventFixTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_removes_time_out_and_the_event_is_charged_again(): void
    {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $admin = User::role('admin')->first();
        $tappedIn = User::role('student')->first();
        $absent = User::create(['name' => 'Absent, Ana', 'username' => 'LGC25-7001', 'password' => 'x', 'department_id' => $tappedIn->department_id]);
        $absent->assignRole('student');
        $paidUp = User::create(['name' => 'Paid, Pia', 'username' => 'LGC25-7002', 'password' => 'x', 'department_id' => $tappedIn->department_id]);
        $paidUp->assignRole('student');

        $first = Semester::create(['name' => '1st Sem', 'starts_at' => now()->subMonth()]);
        $event = Event::create(['department_id' => $tappedIn->department_id, 'name' => 'Nurses Week, Day 1', 'start_time' => now()->subHours(3),
            'end_time' => now()->subHours(2), 'missed_in_fine' => 50, 'missed_out_fine' => 50, 'late_fine' => 20]);
        $session = $event->sessions->first();
        foreach ([$tappedIn, $paidUp] as $student) {
            Attendance::create(['event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $student->id, 'status' => 'present', 'time_in_at' => now()->subHours(3)]);
        }
        $event->markAbsentees(); // no time out for both, absent ₱100
        Fine::where('user_id', $paidUp->id)->update(['status' => 'paid']);
        Semester::create(['name' => '2nd Sem', 'starts_at' => now()]); // fixed later, in a new semester

        $this->actingAs($admin)->get(route('admin.events'))->assertOk()->assertSee('Nurses Week, Day 1');
        $this->get(route('admin.events.edit', $event))->assertOk();

        $this->put(route('admin.events.reprocess', $event), [
            'sessions' => [$session->id => ['has_in' => '1']], // time out unticked
            'missed_in_fine' => 50, 'missed_out_fine' => 50, 'late_fine' => 20,
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.events.edit', $event))
            ->assertSessionHas('refunds', fn ($r) => count($r) === 1 && $r[0]['username'] === 'LGC25-7002' && $r[0]['reason'] === 'No time out (' . strtolower($session->label()) . ')');

        $this->assertSame(0, Fine::where('user_id', $tappedIn->id)->count()); // no time out fine anymore
        $this->assertEquals(50, Fine::where('user_id', $absent->id)->sole()->amount); // absent = the time in only
        $this->assertSame($first->id, Fine::where('user_id', $absent->id)->value('semester_id')); // kept in its semester
        $this->assertSame('paid', Fine::where('user_id', $paidUp->id)->sole()->status); // paid one kept, nothing new
        $this->assertFalse($session->fresh()->has_out);
        $this->assertSame('Fixed and charged an event again', ActivityLog::latest('id')->value('action'));

        // At least one tap per session; officers can't use it; unprocessed events can't be "fixed"
        $this->put(route('admin.events.reprocess', $event), ['sessions' => [], 'missed_in_fine' => 50, 'missed_out_fine' => 50, 'late_fine' => 20])
            ->assertSessionHasErrors("sessions.{$session->id}");
        $this->actingAs(User::role('officer')->first())->get(route('admin.events.edit', $event))->assertForbidden();
        $open = Event::create(['department_id' => $tappedIn->department_id, 'name' => 'Later', 'start_time' => now()->addDay(), 'end_time' => now()->addDay()->addHour()]);
        $this->actingAs($admin)->get(route('admin.events.edit', $open))->assertNotFound();
    }

    public function test_reprocessing_the_same_settings_changes_nothing(): void
    {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $student = User::role('student')->first();
        $event = Event::create(['department_id' => $student->department_id, 'name' => 'Assembly', 'start_time' => now()->subHours(3),
            'end_time' => now()->subHours(2), 'missed_in_fine' => 50, 'missed_out_fine' => 50]);
        Attendance::create(['event_id' => $event->id, 'event_session_id' => $event->sessions->first()->id, 'user_id' => $student->id, 'status' => 'missed_in', 'time_out_at' => now()->subHours(2)]);
        $event->markAbsentees();
        $before = Fine::pluck('reason', 'user_id')->all();

        $event->reprocess();

        $this->assertSame($before, Fine::pluck('reason', 'user_id')->all());
        $this->assertSame('missed_in', Attendance::sole()->status);
    }
}
