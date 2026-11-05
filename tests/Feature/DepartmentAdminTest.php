<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $officer;
    private Department $sit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->admin = User::role('admin')->first();
        $this->officer = User::role('officer')->first();
        $this->sit = $this->officer->department;
    }

    private function logIn(string $username, string $password)
    {
        auth()->logout();
        return $this->post('/login', ['username' => $username, 'password' => $password]);
    }

    public function test_admin_edits_name_and_code(): void
    {
        $this->actingAs($this->admin)->put(route('admin.departments.update', $this->sit), ['name' => 'College of Nursing', 'code' => 'CON'])
            ->assertSessionHasNoErrors();
        $this->assertSame('CON', $this->sit->fresh()->code);

        $this->put(route('admin.departments.update', $this->sit), ['name' => 'College of Nursing', 'code' => 'SOA'])
            ->assertSessionHasErrors('code');
        $this->put(route('admin.departments.update', $this->sit), ['name' => 'Renamed', 'code' => 'CON'])
            ->assertSessionHasNoErrors(); // its own code is not a duplicate
    }

    public function test_archiving_blocks_its_users_and_restoring_allows_them(): void
    {
        $this->actingAs($this->admin)->post(route('admin.departments.toggleArchive', $this->sit));
        $this->assertNotNull($this->sit->fresh()->archived_at);

        $this->logIn('SIT_Officer', 'password')->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->logIn('LGC25-105680', 'Student105680')->assertSessionHasErrors('username');
        $this->logIn('superadmin', 'password')->assertRedirect('dashboard'); // admins have no department

        $this->actingAs($this->admin)->post(route('admin.departments.toggleArchive', $this->sit));
        $this->assertNull($this->sit->fresh()->archived_at);
        $this->logIn('SIT_Officer', 'password')->assertRedirect('dashboard');
    }

    public function test_archived_department_cannot_get_new_officers(): void
    {
        $this->sit->update(['archived_at' => now()]);

        $this->actingAs($this->admin)->get(route('admin.officers'))->assertOk()->assertDontSee('School of Information and Technology');
        $this->post(route('admin.officers'), ['name' => 'New', 'username' => 'new_officer', 'password' => 'password123', 'department_id' => $this->sit->id])
            ->assertSessionHasErrors('department_id');
    }

    public function test_officers_cannot_manage_departments(): void
    {
        $this->actingAs($this->officer);
        $this->get(route('admin.departments.edit', $this->sit))->assertForbidden();
        $this->put(route('admin.departments.update', $this->sit), ['name' => 'X', 'code' => 'X'])->assertForbidden();
        $this->post(route('admin.departments.toggleArchive', $this->sit))->assertForbidden();
        $this->assertNull($this->sit->fresh()->archived_at);
    }
}
