<?php

namespace Tests\Feature;

use App\Models\Attendance;
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

    public function test_admin_starts_a_new_semester(): void
    {
        $other = $this->newStudent('LGC25-200000'); // Second Year

        $this->actingAs($this->officer)->post(route('admin.semesters'), ['name' => 'x', 'starts_at' => now()->toDateString()])
            ->assertForbidden();

        $this->actingAs($this->admin)->post(route('admin.semesters'), [
            'name' => '2nd Sem 2026-2027',
            'starts_at' => now()->toDateString(),
            'deactivate_year_level' => 'First Year',
        ])->assertSessionHasNoErrors();

        $this->assertSame('2nd Sem 2026-2027', Semester::current()->name);
        $this->get(route('admin.semesters'))->assertOk()->assertSee('2nd Sem 2026-2027');
        $this->assertFalse((bool) $this->student->fresh()->is_active);
        $this->assertTrue((bool) $other->fresh()->is_active);

        // A new semester can't start on or before the current one
        $this->post(route('admin.semesters'), ['name' => 'Typo', 'starts_at' => now()->subYear()->toDateString()])
            ->assertSessionHasErrors('starts_at');
    }

    public function test_fines_are_filed_under_the_current_semester(): void
    {
        $semester = Semester::create(['name' => '1st Sem', 'starts_at' => now()->subMonth()]);
        $event = Event::create([
            'department_id' => $this->student->department_id,
            'name' => 'Past',
            'start_time' => now()->subHours(2),
            'end_time' => now()->subHour(),
            'missed_in_fine' => 30,
        ]);

        $event->markAbsentees();

        $this->assertSame($semester->id, Fine::where('user_id', $this->student->id)->sole()->semester_id);
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
            'missed_in_fine' => 30,
        ]);
        $ended->markAbsentees();
        $this->assertSame(0, Attendance::where('user_id', $this->student->id)->count());
        $this->assertSame(0, Fine::where('user_id', $this->student->id)->count());

        $running = Event::create([
            'department_id' => $this->student->department_id,
            'name' => 'Now',
            'start_time' => now()->subHour(),
            'end_time' => now()->addHour(),
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
