<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
        $same = ['username' => $student->username, 'year_level' => 'First Year'];

        $this->put(route('students.update', $student), ['name' => 'Renamed', 'rfid_uid' => '04:a1:b2:c3'] + $same)
            ->assertSessionHasNoErrors()->assertRedirect(route('students.index'));
        $this->assertSame('Renamed', $student->fresh()->name);
        $this->assertSame('04A1B2C3', $student->fresh()->rfid_uid);

        // Card already belongs to another student
        $this->put(route('students.update', $student), ['name' => 'Renamed', 'rfid_uid' => 'AA:BB:CC:DD'] + $same)
            ->assertSessionHasErrors('rfid_uid');

        // Saving the student's own card again is fine; clearing removes it
        $this->put(route('students.update', $student), ['name' => 'Renamed', 'rfid_uid' => '04:A1:B2:C3'] + $same)->assertSessionHasNoErrors();
        $this->put(route('students.update', $student), ['name' => 'Renamed', 'rfid_uid' => ''] + $same)->assertSessionHasNoErrors();
        $this->assertNull($student->fresh()->rfid_uid);

        $soa = User::create(['name' => 'SOA', 'username' => 'LGC25-888888', 'password' => 'x', 'department_id' => Department::where('code', 'SOA')->value('id')]);
        $soa->assignRole('student');
        $this->get(route('students.edit', $soa))->assertForbidden();
        $this->put(route('students.update', $soa), ['name' => 'Hijack'])->assertForbidden();
    }

    public function test_officer_corrects_id_and_year_level_and_resets_password(): void
    {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $student = User::role('student')->first();
        $student->update(['name' => 'Santos, Ana', 'username' => 'LGC26-6O70', 'year_level' => 'First Year']); // typo'd ID
        $edit = fn ($fields) => $this->actingAs(User::role('officer')->first())->put(route('students.update', $student),
            $fields + ['name' => 'Santos, Ana', 'username' => 'lgc26-6070', 'year_level' => 'Second Year']);

        $edit([])->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => !str_contains($s, 'Password reset'));
        $this->assertSame('LGC26-6070', $student->fresh()->username);
        $this->assertSame('Second Year', $student->fresh()->year_level);

        $edit(['reset_password' => '1'])->assertSessionHas('status', fn ($s) => str_contains($s, 'Password reset to: Santos6070'));
        $this->assertTrue(Hash::check('Santos6070', $student->fresh()->password));

        $edit(['username' => 'LGC-6070'])->assertSessionHasErrors('username');
        $edit(['year_level' => 'Sixth Year'])->assertSessionHasErrors('year_level');
        $taken = User::create(['name' => 'Other', 'username' => 'LGC25-999999', 'password' => 'x', 'department_id' => $student->department_id]);
        $edit(['username' => 'LGC25-999999'])->assertSessionHasErrors('username');
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
