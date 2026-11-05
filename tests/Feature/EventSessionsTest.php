<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Fine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Morning / afternoon / evening sessions, each tapped in and out by time window.
 */
class EventSessionsTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;
    private User $student;
    private Carbon $day;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->officer = User::role('officer')->first();
        $this->student = User::role('student')->first();
        $this->day = now()->addDay()->startOfDay();
    }

    private function form(array $sessions, array $extra = []): array
    {
        return $extra + [
            'name' => 'Nurses Week, Day 1',
            'date' => $this->day->toDateString(),
            'sessions' => $sessions,
            'absent_fine' => 50,
            'late_fine' => 20,
            'missed_in_fine' => 15,
            'missed_out_fine' => 10,
        ];
    }

    /** Morning 8–12 and afternoon 1–5 on $this->day, created through the form. */
    private function dayEvent(): Event
    {
        $this->actingAs($this->officer)->post(route('events.store'), $this->form([
            'morning' => ['enabled' => '1', 'start' => '08:00', 'end' => '12:00'],
            'afternoon' => ['enabled' => '1', 'start' => '13:00', 'end' => '17:00'],
        ]))->assertSessionHasNoErrors();

        return Event::latest('id')->first();
    }

    private function tapAt(Event $event, string $time, ?User $student = null)
    {
        $this->travelTo($this->day->copy()->setTimeFromTimeString($time));

        return $this->actingAs($this->officer)->postJson(route('attendance.logRfid', $event), ['rfid_uid' => ($student ?? $this->student)->rfid_uid]);
    }

    private function row(Event $event, string $session, ?User $student = null): ?Attendance
    {
        return Attendance::where('event_session_id', $event->sessions->firstWhere('name', $session)->id)
            ->where('user_id', ($student ?? $this->student)->id)->first();
    }

    public function test_officer_creates_a_day_with_sessions(): void
    {
        $event = $this->dayEvent();

        $this->assertSame(['morning', 'afternoon'], $event->sessions->pluck('name')->all());
        $this->assertSame('08:00', $event->start_time->format('H:i'));
        $this->assertSame('17:00', $event->end_time->format('H:i'));
        $this->assertEquals(15, $event->missed_in_fine);

        $this->post(route('events.store'), $this->form([]))->assertSessionHasErrors('sessions');
        $this->post(route('events.store'), $this->form([
            'morning' => ['enabled' => '1', 'start' => '08:00', 'end' => '13:30'],
            'afternoon' => ['enabled' => '1', 'start' => '13:00', 'end' => '17:00'],
        ]))->assertSessionHasErrors('sessions.afternoon');
        $this->post(route('events.store'), $this->form(['evening' => ['enabled' => '1', 'start' => '18:00', 'end' => '18:30']]))
            ->assertSessionHasErrors('sessions.evening');
    }

    public function test_taps_are_time_in_then_time_out_by_window(): void
    {
        $event = $this->dayEvent();

        $this->tapAt($event, '06:59')->assertStatus(422); // time in opens 7:00
        $this->tapAt($event, '07:55')->assertJson(['action' => 'in', 'status' => 'present', 'session' => 'Morning']);
        $this->tapAt($event, '09:00')->assertJson(['repeat' => true]);
        $this->tapAt($event, '11:40')->assertJson(['action' => 'out', 'session' => 'Morning']);

        $morning = $this->row($event, 'morning');
        $this->assertSame('07:55', $morning->time_in_at->format('H:i'));
        $this->assertSame('11:40', $morning->time_out_at->format('H:i'));

        $this->tapAt($event, '13:05')->assertJson(['action' => 'in', 'status' => 'late', 'session' => 'Afternoon']);
    }

    public function test_overlapping_out_and_in_windows(): void
    {
        $event = $this->dayEvent();
        $other = User::create(['name' => 'Other', 'username' => 'LGC25-200001', 'password' => 'x', 'department_id' => $this->student->department_id, 'rfid_uid' => 'CAFE0001']);
        $other->assignRole('student');

        // 12:15: morning time out (11:30–12:30) and afternoon time in (from 12:00) are both open
        $this->tapAt($event, '08:30');
        $this->tapAt($event, '12:15')->assertJson(['action' => 'out', 'session' => 'Morning']);
        $this->tapAt($event, '12:15', $other)->assertJson(['action' => 'in', 'session' => 'Afternoon', 'status' => 'present']);

        // Tapping again doesn't record a morning time out for someone who has moved on to the afternoon
        $this->tapAt($event, '12:16', $other)->assertJson(['repeat' => true, 'session' => 'Afternoon', 'at' => '12:15:00 PM']);
        $this->assertNull($this->row($event, 'morning', $other));
    }

    public function test_processing_fines_each_kind_of_missed_or_late_tap(): void
    {
        $event = $this->dayEvent();

        $this->tapAt($event, '08:10');  // morning: late in
        $this->tapAt($event, '11:45');  // morning: out
        $this->tapAt($event, '16:50');  // afternoon: only tapped out

        $this->travelTo($this->day->copy()->setTime(17, 31));
        $this->post(route('events.processFines', $event))->assertSessionHas('status');

        $reasons = Fine::where('user_id', $this->student->id)->orderBy('id')->get()->map(fn ($f) => $f->reason . ' ' . number_format($f->amount, 2))->all();
        $this->assertSame(['Late (morning) 20.00', 'No time in (afternoon) 15.00'], $reasons);
        $this->assertSame('missed_in', $this->row($event, 'afternoon')->status);
    }

    public function test_absent_and_no_time_out(): void
    {
        $event = $this->dayEvent();
        $this->tapAt($event, '07:30'); // morning in, never out; no afternoon taps

        $this->travelTo($this->day->copy()->setTime(17, 31));
        $event->markAbsentees();

        $this->assertSame(['No time out (morning)', 'Absent (afternoon)'], Fine::orderBy('id')->pluck('reason')->all());
        $this->assertSame('absent', $this->row($event, 'afternoon')->status);
    }

    private function sessionOf(Event $event, string $name)
    {
        return $event->sessions()->where('name', $name)->first();
    }

    public function test_officer_starts_any_session_early(): void
    {
        $event = $this->dayEvent();

        $this->tapAt($event, '06:00')->assertStatus(422); // usually opens at 7:00
        $this->actingAs($this->officer)->get(route('events.show', $event))->assertSee('Start now');

        $this->post(route('events.sessions.start', [$event, $this->sessionOf($event, 'morning')]))->assertRedirect(route('attendance.rfid', $event));
        $this->assertSame('06:00', $this->sessionOf($event, 'morning')->in_opens_at->format('H:i'));
        $this->assertNull($this->sessionOf($event, 'afternoon')->in_opens_at);
        $this->assertSame('live', $event->fresh()->phase());

        $this->tapAt($event, '06:05')->assertJson(['action' => 'in', 'status' => 'present', 'session' => 'Morning']);
        $this->tapAt($event, '11:40')->assertJson(['action' => 'out']); // time out unchanged

        // The afternoon can be started early on its own (it usually opens at 12:00)
        $this->travelTo($this->day->copy()->setTime(11, 45));
        $this->post(route('events.sessions.start', [$event, $this->sessionOf($event, 'afternoon')]));
        $this->assertSame('11:45', $this->sessionOf($event, 'afternoon')->in_opens_at->format('H:i'));
    }

    public function test_officer_ends_a_session_early(): void
    {
        $event = $this->dayEvent();
        $morning = $this->sessionOf($event, 'morning');

        $this->travelTo($this->day->copy()->setTime(6, 0));
        $this->actingAs($this->officer)->post(route('events.sessions.end', [$event, $morning]))->assertSessionHas('error'); // not open yet

        $this->tapAt($event, '08:05');
        $this->travelTo($this->day->copy()->setTime(10, 0));
        $this->actingAs($this->officer)->post(route('events.sessions.end', [$event, $morning]))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHas('status', 'Morning closed. No more taps are recorded for it.');

        $this->assertSame('10:00', $morning->fresh()->closed_at->format('H:i'));
        $this->assertSame('12:00', $morning->fresh()->ends_at->format('H:i')); // scheduled times kept
        $this->tapAt($event, '10:05')->assertStatus(422); // closed at once: no time-out window
        $this->tapAt($event, '11:45')->assertStatus(422); // not even in its usual time-out window
        $this->tapAt($event, '12:30')->assertJson(['action' => 'in', 'session' => 'Afternoon']); // afternoon unaffected
    }

    public function test_officer_ends_the_whole_event_early(): void
    {
        $event = $this->dayEvent();
        $this->tapAt($event, '07:55'); // morning in, never out

        $this->travelTo($this->day->copy()->setTime(10, 0));
        $this->actingAs($this->officer)->post(route('events.end', $event))
            ->assertSessionHas('status', 'Event ended. Morning closed; Afternoon cancelled. You can process absences now.');

        $event = $event->fresh();
        $this->assertSame(['morning'], $event->sessions->pluck('name')->all());
        $this->assertFalse($event->isOpenForAttendance());
        $this->tapAt($event, '10:01')->assertStatus(422);

        // Processing works straight away, and the missing time out is fined
        $this->travelTo($this->day->copy()->setTime(10, 1));
        $this->post(route('events.processFines', $event))->assertSessionHas('status');
        $this->assertSame(['No time out (morning)'], \App\Models\Fine::pluck('reason')->all());
    }

    public function test_ending_an_event_with_a_session_started_early(): void
    {
        // Morning started early at 6:00 and has a tap, before its 8:00 start
        $event = $this->dayEvent();
        $this->travelTo($this->day->copy()->setTime(6, 0));
        $this->actingAs($this->officer)->post(route('events.sessions.start', [$event, $this->sessionOf($event, 'morning')]));
        $this->tapAt($event, '06:10');

        $this->post(route('events.end', $event))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHas('status', 'Event ended. Morning closed; Afternoon cancelled. You can process absences now.');

        $this->assertSame(['morning'], $event->fresh()->sessions->pluck('name')->all());
        $this->tapAt($event, '06:15')->assertStatus(422);
    }

    public function test_scheduled_processing_picks_up_an_event_ended_early(): void
    {
        $event = $this->dayEvent();
        $this->tapAt($event, '07:55');
        $this->travelTo($this->day->copy()->setTime(10, 0));
        $this->actingAs($this->officer)->post(route('events.end', $event));

        $this->travelTo($this->day->copy()->setTime(10, 5));
        $this->artisan('cleartrack:process-fines')->assertSuccessful();
        $this->assertTrue($event->fresh()->fines_processed);
    }

    public function test_ending_an_event_that_has_not_started_is_refused(): void
    {
        $event = $this->dayEvent();

        $this->travelTo($this->day->copy()->setTime(6, 0));
        $this->actingAs($this->officer)->post(route('events.end', $event))->assertSessionHas('error');
        $this->assertCount(2, $event->fresh()->sessions);
    }

    public function test_early_start_and_end_are_refused_for_other_departments_events_and_finished_events(): void
    {
        $event = $this->dayEvent();
        $other = $this->dayEvent(); // a second event, for the session-belongs-to-event check
        $soaOfficer = User::create(['name' => 'SOA Officer', 'username' => 'soa_officer', 'password' => 'x',
            'department_id' => \App\Models\Department::where('code', 'SOA')->value('id')]);
        $soaOfficer->assignRole('officer');

        $this->travelTo($this->day->copy()->setTime(6, 0));
        $this->actingAs($soaOfficer)->post(route('events.sessions.start', [$event, $this->sessionOf($event, 'morning')]))->assertForbidden();
        $this->actingAs($soaOfficer)->post(route('events.end', $event))->assertForbidden();

        $this->actingAs($this->officer)->post(route('events.sessions.start', [$event, $this->sessionOf($other, 'morning')]))->assertNotFound();

        $event->update(['fines_processed' => true]);
        $this->post(route('events.sessions.start', [$event, $this->sessionOf($event, 'morning')]))->assertForbidden();
        $this->post(route('events.end', $event))->assertSessionHas('error');
        $this->assertNull($this->sessionOf($event, 'morning')->in_opens_at);
    }

    public function test_processed_event_can_no_longer_be_edited(): void
    {
        $event = $this->dayEvent();
        $this->travelTo($this->day->copy()->setTime(17, 31));
        $this->actingAs($this->officer)->get(route('events.show', $event))->assertSee('Edit event');

        $this->post(route('events.processFines', $event));

        $this->get(route('events.show', $event))->assertDontSee('Edit event');
        $this->get(route('events.index'))->assertDontSee(route('events.edit', $event));
        $this->get(route('events.edit', $event))->assertRedirect(route('events.show', $event))->assertSessionHas('error');
        $this->put(route('events.update', $event), $this->form(['morning' => ['enabled' => '1', 'start' => '09:00', 'end' => '12:00']], ['name' => 'Renamed']))
            ->assertSessionHas('error');
        $this->assertSame('Nurses Week, Day 1', $event->fresh()->name);
    }

    public function test_cannot_process_before_last_time_out_closes(): void
    {
        $event = $this->dayEvent();

        $this->travelTo($this->day->copy()->setTime(17, 20)); // afternoon time out open until 17:30
        $this->actingAs($this->officer)->post(route('events.processFines', $event))->assertSessionHas('error');
        $this->assertFalse($event->fresh()->fines_processed);
    }

    public function test_a_session_with_taps_cannot_be_removed(): void
    {
        $event = $this->dayEvent();
        $this->tapAt($event, '07:55');

        $this->put(route('events.update', $event), $this->form(['afternoon' => ['enabled' => '1', 'start' => '13:00', 'end' => '17:00']]))
            ->assertSessionHasErrors('sessions.morning');
        $this->assertCount(2, $event->fresh()->sessions);
    }
}
