<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Students on the default password must choose their own before using ClearTrack.
 */
class FirstLoginPasswordTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $this->officer = User::role('officer')->first();
    }

    private function student(string $password): User
    {
        $student = User::create(['name' => 'Santos, Ana', 'username' => 'LGC26-6578', 'password' => Hash::make($password), 'department_id' => $this->officer->department_id]);
        $student->assignRole('student');

        return $student;
    }

    public function test_student_on_the_default_password_must_change_it_first(): void
    {
        $student = $this->student('Santos6578');

        $this->post('/login', ['username' => 'LGC26-6578', 'password' => 'Santos6578']);
        $this->get(route('dashboard'))->assertRedirect(route('settings'));
        $this->get(route('student.events'))->assertRedirect(route('settings'));
        $this->get(route('settings'))->assertOk()->assertSee('Change your password to continue');

        // The default again doesn't count
        $this->post(route('settings.password'), ['current_password' => 'Santos6578', 'password' => 'Santos6578', 'password_confirmation' => 'Santos6578'])
            ->assertSessionHasErrors('password');

        $this->post(route('settings.password'), ['current_password' => 'Santos6578', 'password' => 'my-own-pass-123', 'password_confirmation' => 'my-own-pass-123'])
            ->assertRedirect(route('dashboard'));
        $this->assertNotNull($student->fresh()->password_changed_at);
        $this->get(route('dashboard'))->assertOk();

        // An officer resetting it to the default makes them change it again
        $this->actingAs($this->officer)->put(route('students.update', $student), ['name' => 'Santos, Ana', 'username' => 'LGC26-6578', 'year_level' => 'First Year', 'reset_password' => '1'])->assertSessionHasNoErrors();
        $this->assertNull($student->fresh()->password_changed_at);
        $this->actingAs($student->fresh())->get(route('dashboard'))->assertRedirect(route('settings'));
    }

    public function test_students_who_already_chose_a_password_and_officers_are_let_through(): void
    {
        $student = $this->student('chose-my-own-1');
        $this->actingAs($student)->get(route('dashboard'))->assertOk();
        $this->assertNotNull($student->fresh()->password_changed_at); // remembered, so it isn't checked again

        $this->actingAs($this->officer)->get(route('dashboard'))->assertOk(); // officers aren't affected
    }
}
