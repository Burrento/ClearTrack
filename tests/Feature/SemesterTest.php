<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ClearancePeriod;
use App\Models\Department;
use App\Models\Event;
use App\Models\Fine;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SemesterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $officer;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->admin = User::role('admin')->first();
        $this->officer = User::role('officer')->first();
        $this->student = User::role('student')->first();
    }

    private function absentFrom(User $student, int $points, $startTime): Event
    {
        $event = Event::create([
            'department_id' => $student->department_id,
            'name' => 'Assembly',
            'start_time' => $startTime,
            'end_time' => $startTime->copy()->addHour(),
            'penalty_points' => $points,
        ]);
        Attendance::create(['event_id' => $event->id, 'user_id' => $student->id, 'status' => 'absent']);

        return $event;
    }

    private function newStudent(string $id, ?int $departmentId = null): User
    {
        $user = User::create([
            'name' => 'Other Student',
            'username' => $id,
            'password' => 'x',
            'department_id' => $departmentId ?? $this->student->department_id,
            'year_level' => 'Second Year',
        ]);
        $user->assignRole('student');

        return $user;
    }

    private function clearanceFines(User $student)
    {
        return Fine::where('user_id', $student->id)->whereNull('event_id')->get();
    }

    public function test_points_only_count_events_since_the_current_semester_started(): void
    {
        Semester::create(['name' => '2nd Sem', 'starts_at' => now()->subDays(10)]);
        $this->absentFrom($this->student, 3, now()->subDays(20));
        $this->absentFrom($this->student, 2, now()->subDays(5));

        $this->assertSame(2, $this->student->getTotalPenaltyPoints());
    }

    public function test_reprocessing_fines_keeps_paid_fines_and_does_not_refine_payers(): void
    {
        Semester::create(['name' => '1st Sem', 'starts_at' => now()->subDays(10)]);
        $other = $this->newStudent('LGC25-200000');
        $this->absentFrom($this->student, 2, now()->subDays(5));
        Attendance::create(['event_id' => Event::first()->id, 'user_id' => $other->id, 'status' => 'absent']);

        $this->actingAs($this->officer)->post(route('fines-settings.storeTiers'), [
            'tiers' => [['min_points' => 1, 'max_points' => null, 'fine_amount' => 50]],
        ]);
        $this->post(route('fines-settings.process'));

        $paid = $this->clearanceFines($this->student)->sole();
        $paid->update(['status' => 'paid']);
        $this->post(route('clearance.clear', $this->student))->assertSessionHasNoErrors();

        $this->post(route('fines-settings.process'));

        $this->assertSame([$paid->id], $this->clearanceFines($this->student)->pluck('id')->all());
        $this->assertSame('paid', $paid->fresh()->status);
        $this->assertTrue((bool) $this->student->fresh()->is_cleared);
        $this->assertSame('unpaid', $this->clearanceFines($other)->sole()->status);

        $this->post(route('fines-settings.reset'));

        $this->assertNotNull($paid->fresh());
        $this->assertCount(0, $this->clearanceFines($other));
    }

    public function test_admin_starts_a_new_semester(): void
    {
        $this->actingAs($this->officer)->post(route('fines-settings.storeTiers'), [
            'tiers' => [['min_points' => 1, 'max_points' => null, 'fine_amount' => 50]],
        ]);
        $this->student->update(['is_cleared' => true]);
        $other = $this->newStudent('LGC25-200000'); // Second Year

        $this->actingAs($this->officer)->post(route('admin.semesters'), ['name' => 'x', 'starts_at' => now()->toDateString()])
            ->assertForbidden();

        $this->actingAs($this->admin)->post(route('admin.semesters'), [
            'name' => '2nd Sem 2026-2027',
            'starts_at' => now()->toDateString(),
            'deactivate_year_level' => 'First Year',
        ])->assertSessionHasNoErrors();

        $semester = Semester::current();
        $this->assertSame('2nd Sem 2026-2027', $semester->name);
        $this->get(route('admin.semesters'))->assertOk()->assertSee('2nd Sem 2026-2027');
        $this->actingAs($this->officer)->get(route('fines.index'))->assertOk();
        $this->actingAs($this->admin);

        $period = ClearancePeriod::where('department_id', $this->officer->department_id)->where('semester_id', $semester->id)->sole();
        $this->assertSame(50.0, (float) $period->fine_tiers[0]['fine_amount']);
        $this->assertSame('inactive', $period->status);

        $this->assertFalse((bool) $this->student->fresh()->is_cleared);
        $this->assertFalse((bool) $this->student->fresh()->is_active);
        $this->assertTrue((bool) $other->fresh()->is_active);

        // A new semester can't start on or before the current one
        $this->post(route('admin.semesters'), ['name' => 'Typo', 'starts_at' => now()->subYear()->toDateString()])
            ->assertSessionHasErrors('starts_at');
    }

    public function test_deactivated_student_is_locked_out_and_skipped(): void
    {
        $this->student->update(['is_active' => false]);

        $this->post('/login', ['username' => $this->student->username, 'password' => 'Student105680']);
        $this->assertGuest();

        $ended = Event::create([
            'department_id' => $this->student->department_id,
            'name' => 'Past',
            'start_time' => now()->subHours(2),
            'end_time' => now()->subHour(),
            'penalty_points' => 1,
        ]);
        $ended->markAbsentees();
        $this->assertSame(0, Attendance::where('user_id', $this->student->id)->count());

        $running = Event::create([
            'department_id' => $this->student->department_id,
            'name' => 'Now',
            'start_time' => now()->subHour(),
            'end_time' => now()->addHour(),
            'penalty_points' => 1,
        ]);
        $this->actingAs($this->officer)->post(route('attendance.logRfid', $running), ['rfid_uid' => $this->student->rfid_uid])
            ->assertSessionHasErrors('rfid_uid');
    }

    public function test_officer_toggles_only_own_department_students(): void
    {
        $soaStudent = $this->newStudent('LGC25-300000', Department::where('code', 'SOA')->value('id'));

        $this->actingAs($this->officer)->post(route('students.toggleActive', $soaStudent))->assertForbidden();
        $this->assertTrue((bool) $soaStudent->fresh()->is_active);

        $this->post(route('students.toggleActive', $this->student));
        $this->assertFalse((bool) $this->student->fresh()->is_active);

        $this->post(route('students.toggleActive', $this->student));
        $this->assertTrue((bool) $this->student->fresh()->is_active);
    }
}
