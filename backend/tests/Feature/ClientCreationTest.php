<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\Client;

class ClientCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_recruiter_can_create_and_link_a_client(): void
    {
        $company = Company::factory()->create();

        $recruiter = User::factory()->create([
            'role' => 'recruiter',
            'company_id' => $company->id,
        ]);

        Sanctum::actingAs($recruiter);

        $response = $this->postJson('/api/clients', [
            'name' => 'Bright Future Education',
            'email' => 'contact@brightfuture.example',
            'phone' => '0123456789',
        ]);

        $response->assertCreated()
            ->assertJsonPath('client.name', 'Bright Future Education');

        $clientId = $response->json('client.id');

        $this->assertDatabaseHas('clients', [
            'id' => $clientId,
            'name' => 'Bright Future Education',
            'email' => 'contact@brightfuture.example',
            'phone' => '0123456789',
        ]);

        $this->assertDatabaseHas('company_client', [
            'company_id' => $company->id,
            'client_id' => $clientId,
        ]);
    }

    public function test_crew_cannot_create_clients(): void
    {
        $company = Company::factory()->create();

        $crew = User::factory()->create([
            'role' => 'crew',
            'company_id' => $company->id,
        ]);

        Sanctum::actingAs($crew);

        $this->postJson('/api/clients', [
            'name' => 'Test Client',
            'email' => 'client@example.com',
            'phone' => '0123456789',
        ])->assertForbidden();

        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('company_client', 0);
    }

    public function test_recruiter_cannot_submit_company_id_when_creating_client(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $recruiter = User::factory()->create([
            'role' => 'recruiter',
            'company_id' => $company->id,
        ]);

        Sanctum::actingAs($recruiter);

        $this->postJson('/api/clients', [
            'name' => 'New Client',
            'email' => 'newclient@example.com',
            'company_id' => $otherCompany->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id']);

        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('company_client', 0);
    }

    public function test_existing_client_email_does_not_create_a_company_link(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $client = Client::factory()->create([
            'name' => 'Original Client',
            'email' => 'existing@example.com',
        ]);

        $otherCompany->clients()->attach($client->id);

        $recruiter = User::factory()->create([
            'role' => 'recruiter',
            'company_id' => $company->id,
        ]);

        Sanctum::actingAs($recruiter);

        $this->postJson('/api/clients', [
            'name' => 'Attempted Replacement',
            'email' => 'existing@example.com',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('clients', 1);

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'Original Client',
        ]);

        $this->assertDatabaseMissing('company_client', [
            'company_id' => $company->id,
            'client_id' => $client->id,
        ]);

        $this->assertDatabaseHas('company_client', [
            'company_id' => $otherCompany->id,
            'client_id' => $client->id,
        ]);
    }

    public function test_client_list_only_returns_linked_clients(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $ownClient = Client::factory()->create();
        $sharedClient = Client::factory()->create();
        $otherClient = Client::factory()->create();

        $company->clients()->attach([
            $ownClient->id,
            $sharedClient->id,
        ]);

        $otherCompany->clients()->attach([
            $sharedClient->id,
            $otherClient->id,
        ]);

        $recruiter = User::factory()->create([
            'role' => 'recruiter',
            'company_id' => $company->id,
        ]);

        Sanctum::actingAs($recruiter);

        $response = $this->getJson('/api/clients');

        $response->assertOk()
            ->assertJsonCount(2, 'clients');

        $this->assertEqualsCanonicalizing(
            [$ownClient->id, $sharedClient->id],
            array_column($response->json('clients'), 'id')
        );
    }

    public function test_client_search_only_returns_matching_linked_clients(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $matchingClient = Client::factory()->create([
            'name' => 'XYZ Corporation',
        ]);

        $unrelatedClient = Client::factory()->create([
            'name' => 'Bright Future Education',
        ]);

        $otherClient = Client::factory()->create([
            'name' => 'XYZ Events',
        ]);

        $company->clients()->attach([
            $matchingClient->id,
            $unrelatedClient->id,
        ]);

        $otherCompany->clients()->attach($otherClient->id);

        $recruiter = User::factory()->create([
            'role' => 'recruiter',
            'company_id' => $company->id,
        ]);

        Sanctum::actingAs($recruiter);

        $this->getJson('/api/clients?search=XYZ')
            ->assertOk()
            ->assertJsonCount(1, 'clients')
            ->assertJsonPath('clients.0.id', $matchingClient->id)
            ->assertJsonPath('clients.0.name', 'XYZ Corporation')
            ->assertJsonPath('pagination.total', 1);
    }

    public function test_linked_clients_are_paginated_by_name(): void
    {
        $company = Company::factory()->create();

        $beta = Client::factory()->create([
            'name' => 'Beta Events',
        ]);

        $alpha = Client::factory()->create([
            'name' => 'Alpha Education',
        ]);

        $company->clients()->attach([$beta->id, $alpha->id]);

        $recruiter = User::factory()->create([
            'role' => 'recruiter',
            'company_id' => $company->id,
        ]);

        Sanctum::actingAs($recruiter);

        $this->getJson('/api/clients?per_page=1&page=1')
            ->assertOk()
            ->assertJsonCount(1, 'clients')
            ->assertJsonPath('clients.0.id', $alpha->id)
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('pagination.last_page', 2)
            ->assertJsonPath('pagination.per_page', 1)
            ->assertJsonPath('pagination.total', 2);

        $this->getJson('/api/clients?per_page=1&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'clients')
            ->assertJsonPath('clients.0.id', $beta->id)
            ->assertJsonPath('pagination.current_page', 2)
            ->assertJsonPath('pagination.total', 2);
    }
}