<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Event;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceSecurityTest extends TestCase
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

    private function event(array $attrs = []): Event
    {
        return Event::create($attrs + [
            'department_id' => $this->student->department_id,
            'name' => 'Assembly',
            'start_time' => now()->subHour(),
            'end_time' => now()->addHour(),
        ]);
    }

    private function attendanceStatus(Event $event, User $user): ?string
    {
        return Attendance::where(['event_id' => $event->id, 'user_id' => $user->id])->value('status');
    }

    public function test_officer_cannot_scan_into_another_departments_event(): void
    {
        $other = $this->event(['department_id' => Department::where('code', 'SOA')->value('id')]);

        $this->actingAs($this->officer)->post(route('attendance.logRfid', $other), ['rfid_uid' => $this->student->rfid_uid])
            ->assertForbidden();
        $this->assertNull($this->attendanceStatus($other, $this->student));
    }

    // Item 3
    public function test_survey_rejects_foreign_questions_and_departments(): void
    {
        $event = $this->event();
        $survey = Survey::create(['event_id' => $event->id, 'title' => 'Feedback']);
        $own = $survey->questions()->create(['question_text' => 'How was it?']);

        $otherSurvey = Survey::create(['event_id' => $this->event()->id, 'title' => 'Other']);
        $foreign = $otherSurvey->questions()->create(['question_text' => 'Not yours']);

        $this->actingAs($this->student)->post(route('surveys.show', $survey), [
            'answers' => [$own->id => 'Good', $foreign->id => 'Injected'],
        ])->assertSessionHasErrors('answers');
        $this->assertSame(0, SurveyResponse::count());

        $soaSurvey = Survey::create([
            'event_id' => $this->event(['department_id' => Department::where('code', 'SOA')->value('id')])->id,
            'title' => 'SOA',
        ]);
        $this->actingAs($this->student)->get(route('surveys.show', $soaSurvey))->assertForbidden();

        $this->actingAs($this->student)->post(route('surveys.show', $survey), [
            'answers' => [$own->id => 'Good'],
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, SurveyResponse::count());
    }

    // Item 4
    public function test_login_is_throttled(): void
    {
        foreach (range(1, 5) as $_) {
            $this->post('/login', ['username' => 'LGC25-105680', 'password' => 'wrong'])->assertRedirect();
        }

        $this->post('/login', ['username' => 'LGC25-105680', 'password' => 'wrong'])->assertTooManyRequests();
    }

    // Item 5
    public function test_attendance_is_refused_outside_the_event_window(): void
    {
        // Check-in opens an hour before the start, so two hours ahead is still closed
        $upcoming = $this->event(['start_time' => now()->addHours(2), 'end_time' => now()->addHours(3)]);
        $processed = $this->event(['start_time' => now()->subHours(2), 'end_time' => now()->subHour()]);
        $processed->markAbsentees();

        foreach ([$upcoming, $processed] as $event) {
            $this->actingAs($this->officer)->post(route('attendance.logRfid', $event), ['rfid_uid' => $this->student->rfid_uid])
                ->assertSessionHasErrors('rfid_uid');
        }

        $this->assertNull($this->attendanceStatus($upcoming, $this->student));
        $this->assertSame('absent', $this->attendanceStatus($processed, $this->student));
    }

    public function test_tap_before_start_is_present_and_after_start_is_late(): void
    {
        $early = $this->event(['start_time' => now()->addMinutes(30), 'end_time' => now()->addHours(2)]);
        $started = $this->event();

        $this->actingAs($this->officer)->post(route('attendance.logRfid', $early), ['rfid_uid' => $this->student->rfid_uid])
            ->assertSessionHasNoErrors();
        $this->post(route('attendance.logRfid', $started), ['rfid_uid' => $this->student->rfid_uid])
            ->assertSessionHas('scanned', fn ($s) => $s['status'] === 'late' && !$s['repeat']);

        $this->assertSame('present', $this->attendanceStatus($early, $this->student));
        $this->assertSame('late', $this->attendanceStatus($started, $this->student));
    }

    public function test_tapping_again_keeps_the_first_status(): void
    {
        $event = $this->event(['start_time' => now()->addMinutes(30), 'end_time' => now()->addHours(2)]);
        $tap = fn () => $this->actingAs($this->officer)->post(route('attendance.logRfid', $event), ['rfid_uid' => $this->student->rfid_uid]);

        $tap();
        $this->travel(45)->minutes(); // now after the start
        $tap()->assertSessionHas('scanned', fn ($s) => $s['repeat'] && $s['status'] === 'present');

        $this->assertSame('present', $this->attendanceStatus($event, $this->student));
    }
}
