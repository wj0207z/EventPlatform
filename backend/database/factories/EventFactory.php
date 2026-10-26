<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Company;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{

    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'company_id' => Company::factory(),
            'name' => 'Annual Dinner',
            'description' => 'An annual dinner event for our valued clients.',
            'event_date' => now()->addMonth()->toDateString(),
            'location' => '123 Main Street, Kuala Lumpur',
            'status' => 'planning',
        ];
    }
}
