<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ClearancePeriod;
use App\Models\Event;
use App\Models\Fine;
use App\Models\Semester;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every page renders for its role with realistic data (live event, open clearance, unpaid fine).
 */
class PagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_renders(): void
    {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $admin = User::role('admin')->first();
        $officer = User::role('officer')->first();
        $student = User::role('student')->first();

        $semester = Semester::create(['name' => '1st Sem', 'starts_at' => now()->subMonth()]);
        $live = Event::create(['department_id' => $officer->department_id, 'name' => 'Assembly', 'start_time' => now()->subHour(), 'end_time' => now()->addHour(), 'penalty_points' => 2, 'requires_survey' => true]);
        $past = Event::create(['department_id' => $officer->department_id, 'name' => 'Seminar', 'start_time' => now()->subDays(3), 'end_time' => now()->subDays(3)->addHours(2), 'penalty_points' => 3]);
        $survey = Survey::create(['event_id' => $live->id, 'title' => 'Feedback']);
        $survey->questions()->create(['question_text' => 'How was it?']);
        Attendance::create(['event_id' => $past->id, 'user_id' => $student->id, 'status' => 'absent']);
        Attendance::create(['event_id' => $live->id, 'user_id' => $student->id, 'status' => 'present', 'verified_by_id' => $officer->id]);
        ClearancePeriod::create(['department_id' => $officer->department_id, 'semester_id' => $semester->id, 'status' => 'active', 'activated_at' => now(),
            'fine_tiers' => [['min_points' => 1, 'max_points' => null, 'fine_amount' => 50, 'requirements' => 'Letter of apology']]]);
        Fine::create(['user_id' => $student->id, 'semester_id' => $semester->id, 'amount' => 50, 'status' => 'unpaid', 'reason' => 'Clearance Period — 3 pts']);

        $this->get('/login')->assertOk();

        $this->actingAs($student);
        foreach (['dashboard', 'student.events', 'settings'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('surveys.show', $survey))->assertOk();
        $this->get(route('dashboard'))->assertSee('Not cleared')->assertSee('Letter of apology');

        $this->actingAs($officer);
        foreach (['dashboard', 'events.index', 'events.create', 'students.index', 'fines.index', 'fines-settings.index', 'clearance.index', 'settings'] as $route) {
            $this->get(route($route))->assertOk();
        }
        foreach (['events.show', 'events.edit', 'attendance.rfid', 'surveys.create'] as $route) {
            $this->get(route($route, $live))->assertOk();
        }
        $this->get(route('dashboard'))->assertSee('Happening now')->assertSee('Open scanner');

        $this->actingAs($admin);
        foreach (['dashboard', 'admin.semesters', 'admin.departments', 'admin.officers', 'settings'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('admin.departments.edit', $officer->department))->assertOk();
    }
}
