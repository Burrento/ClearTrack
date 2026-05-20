<?php

namespace Tests\Feature;

use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $officer = \App\Models\User::role('officer')->first();

        $response = $this->actingAs($officer)->get('/students');

        $response->assertStatus(200);
    }

    public function test_officers_can_register_students(): void
    {
        $this->withoutMiddleware();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $officer = \App\Models\User::role('officer')->first();
        $department = Department::first();

        $response = $this->actingAs($officer)->post('/students', [
            'name' => 'Test Student',
            'email' => 'student@example.com',
            'password' => 'password',
            'department_id' => $department->id,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($officer);
        $response->assertRedirect(route('students.index'));
        
        $this->assertDatabaseHas('users', [
            'name' => 'Test Student',
            'email' => 'student@example.com',
            'department_id' => $department->id,
        ]);

        $user = \App\Models\User::where('email', 'student@example.com')->first();
        $this->assertTrue($user->hasRole('student'));
    }
}
