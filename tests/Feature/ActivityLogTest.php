<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $this->admin = User::role('admin')->first();
        $this->officer = User::role('officer')->first();
    }

    private function latest(): ActivityLog
    {
        return ActivityLog::latest('id')->first();
    }

    public function test_logins_and_failed_logins_are_logged_without_passwords(): void
    {
        $this->post('/login', ['username' => 'SIT_Officer', 'password' => 'wrong-one']);
        $this->assertSame('Failed login', $this->latest()->action);
        $this->assertSame('SIT_Officer', $this->latest()->subject);
        $this->assertNull($this->latest()->user_id);

        $this->post('/login', ['username' => 'SIT_Officer', 'password' => 'password']);
        $this->assertSame('Logged in', $this->latest()->action);
        $this->assertSame($this->officer->id, $this->latest()->user_id);

        $this->post(route('logout'));
        $this->assertSame('Logged out', $this->latest()->action);
        $this->assertSame($this->officer->id, $this->latest()->user_id); // who it was, though they're now logged out

        $this->assertStringNotContainsString('password', ActivityLog::pluck('subject')->implode(' ') . ActivityLog::pluck('details')->implode(' '));
    }

    public function test_actions_record_who_what_and_the_message_shown(): void
    {
        $this->actingAs($this->officer)->post(route('students.store'), [
            'name' => 'Santos, Ana', 'username' => 'LGC26-6578', 'year_level' => 'First Year',
        ]);
        $log = $this->latest();
        $this->assertSame('Registered a student', $log->action);
        $this->assertSame('Santos, Ana (LGC26-6578)', $log->subject);
        $this->assertStringContainsString('Default password: Santos6578', $log->details);
        $this->assertSame($this->officer->id, $log->user_id);

        // A deleted event is still named in the log
        $event = Event::create(['department_id' => $this->officer->department_id, 'name' => 'Assembly', 'start_time' => now()->addDay(), 'end_time' => now()->addDay()->addHours(2)]);
        $this->delete(route('events.destroy', $event));
        $this->assertSame(['Deleted an event', 'Assembly'], [$this->latest()->action, $this->latest()->subject]);
    }

    public function test_refused_actions_page_views_and_card_taps_are_not_logged(): void
    {
        $this->actingAs($this->officer);
        $this->post(route('students.store'), ['name' => 'Bad', 'username' => 'nope', 'year_level' => 'First Year']); // invalid
        $this->get(route('students.index'));
        $event = Event::create(['department_id' => $this->officer->department_id, 'name' => 'Now', 'start_time' => now()->subMinutes(10), 'end_time' => now()->addHours(2)]);
        $this->postJson(route('attendance.logRfid', $event), ['rfid_uid' => User::role('student')->first()->rfid_uid])->assertOk();
        $this->post(route('admin.backups.store'))->assertForbidden(); // officers can't
        $fine = \App\Models\Fine::create(['user_id' => User::role('student')->first()->id, 'amount' => 50, 'status' => 'unpaid']);
        $this->post(route('fines.markAsPaid', $fine));
        $this->assertSame('paid', $fine->fresh()->status); // marking a fine paid isn't logged

        $this->assertSame(0, ActivityLog::count());
    }

    public function test_admin_views_and_filters_logs_and_others_cannot(): void
    {
        $this->actingAs($this->officer)->post(route('students.store'), ['name' => 'Santos, Ana', 'username' => 'LGC26-6578', 'year_level' => 'First Year']);
        $this->actingAs($this->admin)->post(route('admin.departments'), ['name' => 'College of Nursing', 'code' => 'CON']);

        $this->get(route('admin.logs'))->assertOk()->assertSee('Registered a student')->assertSee('Added a department');
        $this->get(route('admin.logs', ['q' => 'Santos']))->assertSee('Registered a student')->assertDontSee('Added a department');
        $this->get(route('admin.logs', ['role' => 'admin']))->assertSee('Added a department')->assertDontSee('Registered a student');
        $this->get(route('admin.logs', ['date' => now()->subDay()->toDateString()]))->assertSee('Nothing matches these filters.');

        $this->actingAs($this->officer)->get(route('admin.logs'))->assertForbidden();
    }
}
