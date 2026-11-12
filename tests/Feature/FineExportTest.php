<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Event;
use App\Models\Fine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FineExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_officer_exports_only_their_departments_fines_as_csv_and_pdf(): void
    {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $officer = User::role('officer')->first();
        $student = User::role('student')->first();
        $ours = Event::create(['department_id' => $officer->department_id, 'name' => 'Assembly', 'start_time' => now()->subHours(3), 'end_time' => now()->subHours(2)]);
        $theirs = Event::create(['department_id' => Department::where('id', '!=', $officer->department_id)->value('id'), 'name' => 'Other Fair', 'start_time' => now()->subHours(3), 'end_time' => now()->subHours(2)]);
        Fine::create(['user_id' => $student->id, 'event_id' => $ours->id, 'amount' => 50, 'status' => 'unpaid', 'reason' => 'Absent (morning)']);
        Fine::create(['user_id' => $student->id, 'event_id' => $ours->id, 'amount' => 20, 'status' => 'paid', 'reason' => 'Late (afternoon)']);
        Fine::create(['user_id' => $student->id, 'event_id' => $theirs->id, 'amount' => 99, 'status' => 'unpaid']);

        $paidUp = User::create(['name' => 'Paid, Up', 'username' => 'LGC25-9001', 'password' => 'x', 'department_id' => $officer->department_id]);
        $paidUp->assignRole('student');
        Fine::create(['user_id' => $paidUp->id, 'event_id' => $ours->id, 'amount' => 50, 'status' => 'paid']);

        $csv = $this->actingAs($officer)->get(route('fines.export', 'csv'))->assertOk()->streamedContent();
        // One row per student with what they still owe; the paid 20 and the other department's 99 aren't counted
        $this->assertStringContainsString("{$student->username},\"{$student->name}\",Owes,50.00", $csv);
        $this->assertStringNotContainsString('LGC25-9001', $csv); // fully paid: not on the list

        $this->get(route('fines.export', 'pdf'))->assertOk()->assertSee($student->username)->assertSee('₱50.00')->assertDontSee('₱99.00')->assertDontSee('LGC25-9001')->assertDontSee('Assembly');
        $this->get('/fines/export/xlsx')->assertNotFound();
        $this->actingAs($student)->get(route('fines.export', 'csv'))->assertForbidden();
    }
}
