<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\Event;

class EventCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_recruiter_can_create_event_for_linked_client(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create();
        $company->clients()->syncWithoutDetaching([$client->id]);

        $recruiter = User::factory()->create([
            'role' => 'recruiter',
            'company_id' => $company->id,
        ]);

        Sanctum::actingAs($recruiter);

        $response = $this->postJson('/api/events', [
            'client_id' => $client->id,
            'name' => 'Annual Celebration',
            'event_date' => now()->addMonth()->toDateString(),
            'location' => 'Kuala Lumpur',
        ]);

        $response->assertCreated()
            ->assertJsonPath('event.company_id', $company->id)
            ->assertJsonPath('event.client_id', $client->id)
            ->assertJsonPath('event.status', 'planning');

        $this->assertDatabaseHas('events', [
            'id' => $response->json('event.id'),
            'company_id' => $company->id,
            'client_id' => $client->id,
            'name' => 'Annual Celebration',
            'status' => 'planning',
            'description' => null,
        ]);
    }

    public function test_recruiter_cannot_create_event_for_unlinked_client(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $client = Client::factory()->create();

        $otherCompany->clients()->syncWithoutDetaching([
            $client->id,
        ]);

        $recruiter = User::factory()->create([
            'role' => 'recruiter',
            'company_id' => $company->id,
        ]);

        Sanctum::actingAs($recruiter);

        $response = $this->postJson('/api/events', [
            'client_id' => $client->id,
            'name' => 'Unauthorised Client Event',
            'event_date' => now()->addMonth()->toDateString(),
            'location' => 'Kuala Lumpur',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['client_id']);

        $this->assertDatabaseCount('events', 0);
    }

    public function test_crew_cannot_create_events(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create();

        $company->clients()->syncWithoutDetaching([
            $client->id,
        ]);

        $crew = User::factory()->create([
            'role' => 'crew',
            'company_id' => $company->id,
        ]);

        Sanctum::actingAs($crew);

        $response = $this->postJson('/api/events', [
            'client_id' => $client->id,
            'name' => 'Crew Created Event',
            'event_date' => now()->addMonth()->toDateString(),
            'location' => 'Kuala Lumpur',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseCount('events', 0);
    }

    public function test_recruiter_cannot_submit_company_id_when_creating_event(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $client = Client::factory()->create();

        $company->clients()->syncWithoutDetaching([
            $client->id,
        ]);

        $recruiter = User::factory()->create([
            'role' => 'recruiter',
            'company_id' => $company->id,
        ]);

        Sanctum::actingAs($recruiter);

        $response = $this->postJson('/api/events', [
            'client_id' => $client->id,
            'company_id' => $otherCompany->id,
            'name' => 'Wrong Company Event',
            'event_date' => now()->addMonth()->toDateString(),
            'location' => 'Kuala Lumpur',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id']);

        $this->assertDatabaseCount('events', 0);
    }

    public function test_recruiter_cannot_view_another_company_event(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $recruiter = User::factory()->create([
            'role' => 'recruiter',
            'company_id' => $company->id,
        ]);

        $event = Event::factory()->create([
            'company_id' => $otherCompany->id,
        ]);

        Sanctum::actingAs($recruiter);

        $this->getJson("/api/events/{$event->id}")
            ->assertForbidden()
            ->assertJsonMissingPath('event');
    }

    public function test_recruiter_can_view_own_company_event(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create();

        $company->clients()->syncWithoutDetaching([$client->id]);

        $recruiter = User::factory()->create([
            'role' => 'recruiter',
            'company_id' => $company->id,
        ]);

        $event = Event::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'name' => 'Annual Celebration',
        ]);

        Sanctum::actingAs($recruiter);

        $this->getJson("/api/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('event.id', $event->id)
            ->assertJsonPath('event.name', 'Annual Celebration')
            ->assertJsonPath('event.client.id', $client->id)
            ->assertJsonPath('event.client.name', $client->name);
    }

    public function test_recruiter_can_update_event_location_only(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create();

        $company->clients()->syncWithoutDetaching([$client->id]);

        $recruiter = User::factory()->create([
            'role' => 'recruiter',
            'company_id' => $company->id,
        ]);

        $event = Event::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'name' => 'Annual Celebration',
            'description' => 'Original description.',
            'event_date' => '2026-12-15',
            'location' => 'Original Hall',
            'status' => 'planning',
        ]);

        Sanctum::actingAs($recruiter);

        $this->patchJson("/api/events/{$event->id}", [
            'location' => 'New Convention Hall',
        ])
            ->assertOk()
            ->assertJsonPath('event.location', 'New Convention Hall');

        $event->refresh();

        $this->assertSame('New Convention Hall', $event->location);
        $this->assertSame('Annual Celebration', $event->name);
        $this->assertSame('Original description.', $event->description);
        $this->assertSame('2026-12-15', $event->event_date->format('Y-m-d'));
        $this->assertEquals($company->id, $event->company_id);
        $this->assertEquals($client->id, $event->client_id);
        $this->assertSame('planning', $event->status);
    }

    public function test_recruiter_cannot_update_another_company_event(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $recruiter = User::factory()->create([
            'role' => 'recruiter',
            'company_id' => $company->id,
        ]);

        $event = Event::factory()->create([
            'company_id' => $otherCompany->id,
            'location' => 'Original Hall',
        ]);

        Sanctum::actingAs($recruiter);

        $this->patchJson("/api/events/{$event->id}", [
            'location' => 'Changed Without Permission',
        ])->assertForbidden();

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'company_id' => $otherCompany->id,
            'location' => 'Original Hall',
        ]);
    }

    public function test_event_update_rejects_protected_fields(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create();
        $company->clients()->syncWithoutDetaching([$client->id]);

        $otherCompany = Company::factory()->create();
        $otherClient = Client::factory()->create();

        $recruiter = User::factory()->create([
            'role' => 'recruiter',
            'company_id' => $company->id,
        ]);

        $event = Event::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'location' => 'Original Hall',
            'status' => 'planning',
        ]);

        Sanctum::actingAs($recruiter);

        $this->patchJson("/api/events/{$event->id}", [
            'company_id' => $otherCompany->id,
            'client_id' => $otherClient->id,
            'status' => 'completed',
            'location' => 'New Hall',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'company_id',
                'client_id',
                'status',
            ]);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'planning',
            'location' => 'Original Hall',
        ]);
    }

    public function test_event_search_only_returns_matching_company_events(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $recruiter = User::factory()->create([
            'role' => 'recruiter',
            'company_id' => $company->id,
        ]);

        $matchingEvent = Event::factory()->create([
            'company_id' => $company->id,
            'name' => 'Annual Celebration',
        ]);

        Event::factory()->create([
            'company_id' => $company->id,
            'name' => 'Product Launch',
        ]);

        Event::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Annual Celebration',
        ]);

        Sanctum::actingAs($recruiter);

        $this->getJson('/api/events?search=Annual')
            ->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.id', $matchingEvent->id)
            ->assertJsonPath('events.0.company_id', $company->id)
            ->assertJsonPath('pagination.total', 1);
    }
}