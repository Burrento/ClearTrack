<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_password_is_capitalized_last_name_plus_id_digits(): void
    {
        $this->assertSame('Santos105680', User::defaultPassword('Juan SANTOS', 'LGC25-105680'));
        $this->assertSame('Delacruz105680', User::defaultPassword('Dela Cruz, Juan', 'LGC25-105680'));
        $this->assertSame('Peña105680', User::defaultPassword('Ana peña ', 'LGC25-105680'));
    }

    public function test_registered_student_can_log_in_with_default_password(): void
    {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->actingAs(User::role('officer')->first())->post('/students', [
            'name' => 'Maria Reyes',
            'username' => 'LGC25-123456',
            'year_level' => 'First Year',
        ])->assertSessionHas('status', fn ($s) => str_contains($s, 'Default password: Reyes123456'));

        auth()->logout();
        $this->post('/login', ['username' => 'LGC25-123456', 'password' => 'Reyes123456'])->assertRedirect('dashboard');
        $this->assertAuthenticated();
    }

    public function test_officer_can_edit_student_name_and_rfid(): void
    {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $officer = User::role('officer')->first();
        $student = User::role('student')->first();
        $other = User::create(['name' => 'Other', 'username' => 'LGC25-999999', 'password' => 'x', 'department_id' => $student->department_id, 'rfid_uid' => 'AABBCCDD']);
        $other->assignRole('student');

        $this->actingAs($officer)->get(route('students.edit', $student))->assertOk()->assertSee('12:34:56:78:90');

        $this->put(route('students.update', $student), ['name' => 'Renamed', 'rfid_uid' => '04:a1:b2:c3'])
            ->assertSessionHasNoErrors()->assertRedirect(route('students.index'));
        $this->assertSame('Renamed', $student->fresh()->name);
        $this->assertSame('04A1B2C3', $student->fresh()->rfid_uid);

        // Card already belongs to another student
        $this->put(route('students.update', $student), ['name' => 'Renamed', 'rfid_uid' => 'AA:BB:CC:DD'])
            ->assertSessionHasErrors('rfid_uid');

        // Saving the student's own card again is fine; clearing removes it
        $this->put(route('students.update', $student), ['name' => 'Renamed', 'rfid_uid' => '04:A1:B2:C3'])->assertSessionHasNoErrors();
        $this->put(route('students.update', $student), ['name' => 'Renamed', 'rfid_uid' => ''])->assertSessionHasNoErrors();
        $this->assertNull($student->fresh()->rfid_uid);

        $soa = User::create(['name' => 'SOA', 'username' => 'LGC25-888888', 'password' => 'x', 'department_id' => Department::where('code', 'SOA')->value('id')]);
        $soa->assignRole('student');
        $this->get(route('students.edit', $soa))->assertForbidden();
        $this->put(route('students.update', $soa), ['name' => 'Hijack'])->assertForbidden();
    }

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

    public function test_rfid_serial_is_stored_bare_and_displayed_with_colons(): void
    {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $officer = User::role('officer')->first();

        $this->actingAs($officer)->post('/students', [
            'name' => 'Card Student',
            'username' => 'LGC25-105682',
            'rfid_uid' => '04:a1-b2 c3',
            'year_level' => 'First Year',
        ])->assertSessionHasNoErrors();

        $user = User::where('username', 'LGC25-105682')->first();
        $this->assertSame('04A1B2C3', $user->rfid_uid);
        $this->assertSame('04:A1:B2:C3', $user->formattedRfid());
    }

    public function test_student_id_must_match_lgc_format(): void
    {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $officer = User::role('officer')->first();

        foreach (['51410', 'LGC25105680', 'XYZ25-105680', 'LGC26-', 'LGC26--6578', 'LGC 26-6578', 'LGC26-65A8', 'LGC-5985', 'LGC6-5985', 'LGC266-5985'] as $bad) {
            $this->actingAs($officer)->post('/students', [
                'name' => 'Bad ID',
                'username' => $bad,
                'year_level' => 'First Year',
            ])->assertSessionHasErrors('username');
        }
    }
}
