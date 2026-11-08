<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Event;
use App\Models\Fine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminStudentsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $sitStudent;
    private User $soaStudent;
    private Department $soa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $this->admin = User::role('admin')->first();
        $this->sitStudent = User::role('student')->first();
        $this->sitStudent->update(['name' => 'Santos, Ana', 'year_level' => 'First Year']);
        $this->soa = Department::where('code', 'SOA')->first();
        $this->soaStudent = User::create(['name' => 'Reyes, Ben', 'username' => 'LGC24-6001', 'password' => 'x',
            'department_id' => $this->soa->id, 'year_level' => 'Third Year']);
        $this->soaStudent->assignRole('student');
    }

    private function form(User $student, array $changes = []): array
    {
        return $changes + ['name' => $student->name, 'username' => $student->username, 'year_level' => $student->year_level,
            'department_id' => $student->department_id, 'is_active' => '1', 'rfid_uid' => $student->rfid_uid];
    }

    public function test_admin_sees_every_departments_students_and_filters(): void
    {
        $this->actingAs($this->admin)->get(route('admin.students'))->assertOk()
            ->assertSee('Santos, Ana')->assertSee('Reyes, Ben')->assertDontSee(User::role('officer')->first()->username); // students only

        $this->get(route('admin.students', ['department' => $this->soa->id]))->assertSee('Reyes, Ben')->assertDontSee('Santos, Ana');
        $this->get(route('admin.students', ['q' => 'LGC24-6001']))->assertSee('Reyes, Ben')->assertDontSee('Santos, Ana');
        $this->get(route('admin.students', ['year' => 'First Year']))->assertSee('Santos, Ana')->assertDontSee('Reyes, Ben');
        $this->get(route('admin.students', ['status' => 'no_card']))->assertSee('Reyes, Ben')->assertDontSee('Santos, Ana');

        Fine::create(['user_id' => $this->soaStudent->id, 'amount' => 50, 'status' => 'unpaid']);
        $this->get(route('admin.students', ['status' => 'owes']))->assertSee('Reyes, Ben')->assertDontSee('Santos, Ana');
    }

    public function test_admin_edits_moves_deactivates_and_resets_password(): void
    {
        $this->actingAs($this->admin)->get(route('admin.students.edit', $this->soaStudent))->assertOk();

        $this->put(route('admin.students.update', $this->soaStudent), $this->form($this->soaStudent, [
            'username' => 'lgc24-6002', 'year_level' => 'Fourth Year', 'department_id' => $this->sitStudent->department_id,
            'is_active' => null, 'reset_password' => '1',
        ]))->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, 'Password reset to: Reyes6002'));

        $student = $this->soaStudent->fresh();
        $this->assertSame(['LGC24-6002', 'Fourth Year', $this->sitStudent->department_id, false],
            [$student->username, $student->year_level, $student->department_id, (bool) $student->is_active]);
        $this->assertTrue(Hash::check('Reyes6002', $student->password));

        // Can't move a student into an archived department, or take another student's ID
        $this->soa->update(['archived_at' => now()]);
        $this->put(route('admin.students.update', $student), $this->form($student, ['department_id' => $this->soa->id]))->assertSessionHasErrors('department_id');
        $this->put(route('admin.students.update', $student), $this->form($student, ['username' => $this->sitStudent->username]))->assertSessionHasErrors('username');
    }

    public function test_admin_deletes_a_student_with_their_records(): void
    {
        $event = Event::create(['department_id' => $this->soa->id, 'name' => 'Assembly', 'start_time' => now()->subHours(3), 'end_time' => now()->subHours(2)]);
        Attendance::create(['event_id' => $event->id, 'event_session_id' => $event->sessions->first()->id, 'user_id' => $this->soaStudent->id, 'status' => 'absent']);
        Fine::create(['user_id' => $this->soaStudent->id, 'event_id' => $event->id, 'amount' => 50, 'status' => 'paid']);

        $this->actingAs($this->admin)->delete(route('admin.students.destroy', $this->soaStudent))
            ->assertRedirect(route('admin.students'))
            ->assertSessionHas('status', 'Reyes, Ben (LGC24-6001) was deleted, with all their attendance and fines.');

        $this->assertNull(User::find($this->soaStudent->id));
        $this->assertSame(0, Attendance::count());
        $this->assertSame(0, Fine::count());
        $this->assertSame(['Deleted a student', 'Reyes, Ben (LGC24-6001)'], [ActivityLog::latest('id')->value('action'), ActivityLog::latest('id')->value('subject')]);
    }

    public function test_only_students_and_only_admins(): void
    {
        $this->actingAs($this->admin)->delete(route('admin.students.destroy', User::role('officer')->first()))->assertNotFound();
        $this->assertNotNull(User::role('officer')->first());

        $officer = User::role('officer')->first();
        $this->actingAs($officer)->get(route('admin.students'))->assertForbidden();
        $this->actingAs($officer)->delete(route('admin.students.destroy', $this->soaStudent))->assertForbidden();
        $this->assertNotNull($this->soaStudent->fresh());
    }
}
