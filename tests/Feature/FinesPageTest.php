<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Fine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Fines page adds up each student's fines per event, can be searched and filtered, and records
 * one payment for the whole event.
 */
class FinesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_fines_are_one_row_per_student_per_event_and_paid_together(): void
    {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $officer = User::role('officer')->first();
        $ana = User::role('student')->first();
        $ana->update(['name' => 'Santos, Ana', 'year_level' => 'First Year']);
        $ben = User::create(['name' => 'Reyes, Ben', 'username' => 'LGC24-6001', 'password' => 'x', 'department_id' => $officer->department_id, 'year_level' => 'Third Year']);
        $ben->assignRole('student');
        $day1 = Event::create(['department_id' => $officer->department_id, 'name' => 'Nurses Week, Day 1', 'start_time' => now()->subDay(), 'end_time' => now()->subDay()->addHours(8), 'fines_processed' => true]);
        $day2 = Event::create(['department_id' => $officer->department_id, 'name' => 'Nurses Week, Day 2', 'start_time' => now()->subHours(9), 'end_time' => now()->subHour(), 'fines_processed' => true]);
        foreach (['Absent (morning)', 'Absent (afternoon)', 'Absent (evening)'] as $reason) {
            Fine::create(['user_id' => $ana->id, 'event_id' => $day1->id, 'amount' => 100, 'status' => 'unpaid', 'reason' => $reason]);
        }
        Fine::create(['user_id' => $ana->id, 'event_id' => $day2->id, 'amount' => 20, 'status' => 'unpaid', 'reason' => 'Late (morning)']);
        Fine::create(['user_id' => $ben->id, 'event_id' => $day1->id, 'amount' => 50, 'status' => 'paid', 'reason' => 'No time out (morning)']);

        // Ana's three Day 1 fines are one ₱300 row
        $this->actingAs($officer)->get(route('fines.index'))->assertOk()
            ->assertSee('₱300.00')->assertSee('Absent (morning) ₱100.00, Absent (afternoon) ₱100.00, Absent (evening) ₱100.00')
            ->assertSee('3 rows')->assertSee('1 student owe ₱320.00', false);

        $this->get(route('fines.index', ['q' => 'LGC24-6001']))->assertSee('Reyes, Ben')->assertDontSee('Santos, Ana');
        $this->get(route('fines.index', ['event' => $day2->id]))->assertSee('Late (morning)')->assertDontSee('Absent (morning)');
        $this->get(route('fines.index', ['year' => 'Third Year']))->assertSee('Reyes, Ben')->assertDontSee('Santos, Ana');
        $this->get(route('fines.index', ['status' => 'paid']))->assertSee('Reyes, Ben')->assertDontSee('Santos, Ana');

        // One payment covers the whole event, not Day 2
        $this->post(route('fines.payGroup'), ['user_id' => $ana->id, 'event_id' => $day1->id])
            ->assertSessionHas('status', 'Payment of ₱300.00 recorded for Santos, Ana.');
        $this->assertSame(0, Fine::where('event_id', $day1->id)->where('status', 'unpaid')->count());
        $this->assertSame('unpaid', Fine::where('event_id', $day2->id)->value('status'));

        // Nothing left to pay, or another department's student: refused
        $this->post(route('fines.payGroup'), ['user_id' => $ana->id, 'event_id' => $day1->id])->assertNotFound();
        $other = Event::create(['department_id' => \App\Models\Department::where('id', '!=', $officer->department_id)->value('id'), 'name' => 'Other', 'start_time' => now()->subHours(3), 'end_time' => now()->subHours(2)]);
        Fine::create(['user_id' => $ana->id, 'event_id' => $other->id, 'amount' => 10, 'status' => 'unpaid']);
        $this->post(route('fines.payGroup'), ['user_id' => $ana->id, 'event_id' => $other->id])->assertNotFound();
        $this->assertSame('unpaid', Fine::where('event_id', $other->id)->value('status'));
    }
}
