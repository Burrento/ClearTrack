<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_lists_show_20_a_page(): void
    {
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $officer = User::role('officer')->first();
        foreach (range(1, 25) as $day) {
            Event::create(['department_id' => $officer->department_id, 'name' => sprintf('Event %02d', $day),
                'start_time' => now()->subDays($day), 'end_time' => now()->subDays($day)->addHour()]);
        }

        // Newest first: Event 01 to 20 on page 1, the rest on page 2
        $this->actingAs($officer)->get(route('events.index'))->assertOk()
            ->assertSee('Event 20')->assertDontSee('Event 21')->assertSee('Page 1 of 2');
        $this->get(route('events.index', ['page' => 2]))->assertSee('Event 25')->assertDontSee('Event 20');

        $this->actingAs(User::role('student')->first())->get(route('student.events'))->assertOk()
            ->assertSee('Event 20')->assertDontSee('Event 21')->assertSee('Page 1 of 2');
    }
}
