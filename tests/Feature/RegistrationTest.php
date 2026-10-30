<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $officer = User::role('officer')->first();

        $response = $this->actingAs($officer)->get('/students');

        $response->assertStatus(200);
    }

    public function test_officers_can_register_students(): void
    {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $officer = User::role('officer')->first();

        $response = $this->actingAs($officer)->post('/students', [
            'name' => 'New Student',
            'username' => 'lgc25-105681', // lowercase is normalized
            'year_level' => 'First Year',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('students.index'));

        $user = User::where('username', 'LGC25-105681')->first();
        $this->assertNotNull($user);
        $this->assertSame($officer->department_id, $user->department_id);
        $this->assertTrue($user->hasRole('student'));
    }

    public function test_student_id_must_match_lgc_format(): void
    {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $officer = User::role('officer')->first();

        foreach (['51410', 'LGC25105680', 'LGC5-105680', 'LGC25-10568', 'XYZ25-105680'] as $bad) {
            $this->actingAs($officer)->post('/students', [
                'name' => 'Bad ID',
                'username' => $bad,
                'year_level' => 'First Year',
            ])->assertSessionHasErrors('username');
        }
    }
}
