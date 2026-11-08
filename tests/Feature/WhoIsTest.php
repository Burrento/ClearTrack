<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhoIsTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $this->officer = User::role('officer')->first();
    }

    private function lookUp(?string $card)
    {
        return $this->actingAs($this->officer)->get(route('students.whoIs', $card === null ? [] : ['rfid' => $card]));
    }

    public function test_own_department_card_shows_the_student(): void
    {
        $student = User::role('student')->where('department_id', $this->officer->department_id)->first();
        $typed = implode(':', str_split($student->rfid_uid, 2)); // as the reader fills it, with colons

        $this->lookUp($typed)->assertOk()
            ->assertSee('Registered')->assertSee($student->name)->assertSee($student->username)
            ->assertSee(route('students.edit', $student));
        $this->lookUp(strtolower($student->rfid_uid))->assertSee($student->name); // typed by hand
    }

    public function test_other_department_card_hides_the_students_details(): void
    {
        $soa = User::create(['name' => 'Hidden, Person', 'username' => 'LGC25-777777', 'password' => 'x',
            'department_id' => Department::where('code', 'SOA')->value('id'), 'rfid_uid' => 'BEEF0001']);
        $soa->assignRole('student');

        $this->lookUp('BE:EF:00:01')->assertSee('Registered to a student in another department')->assertDontSee('Hidden, Person');
    }

    public function test_unknown_card_and_empty_page(): void
    {
        $this->lookUp(null)->assertOk()->assertSee('Tap a card on the reader')->assertDontSee('Look up')->assertDontSee('No student has this card');
        $this->lookUp('DE:AD:BE:EF')->assertSee('No student has this card')->assertSee('DE:AD:BE:EF');
    }

    public function test_looking_up_records_nothing_and_is_officers_only(): void
    {
        $this->lookUp(User::role('student')->first()->rfid_uid);
        $this->assertSame(0, Attendance::count());
        $this->assertSame(0, ActivityLog::count());

        $this->actingAs(User::role('student')->first())->get(route('students.whoIs'))->assertForbidden();
    }
}
