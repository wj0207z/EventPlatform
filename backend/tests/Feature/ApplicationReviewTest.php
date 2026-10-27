<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Event;
use App\Models\Application;
use App\Models\JobPosting;
use Laravel\Sanctum\Sanctum;

class ApplicationReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        // This method is called before the database is refreshed for each test.
        // You can use it to set up any necessary state or configurations.

        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_company_factory_creates_a_company(): void
    {
        $company = Company::factory()->create([
            'name' => 'Capacity Test Events',
        ]);

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Capacity Test Events',
        ]);
    }

    public function test_recruiter_belongs_to_a_company(): void
    {
        $company = Company::factory()->create([
            'name' => 'Capacity Test Events',
        ]);

        $recruiter = User::factory()->create([
            'name' => 'Test Recruiter',
            'company_id' => $company->id,
            'role' => 'recruiter',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $recruiter->id,
            'company_id' => $company->id,
            'role' => 'recruiter',
        ]);
    }

    public function test_event_belongs_to_a_company(): void
    {
        $company = Company::factory()->create();

        $event = Event::factory()->create([
            'company_id' => $company->id,
        ]);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'company_id' => $company->id,
        ]);

        $this->assertDatabaseHas('clients', [
            'id' => $event->client_id,
        ]);
    }

    public function test_recruiter_cannot_approve_when_job_is_full(): void
    {

        $setup = $this->createJobForRecruiter();

        $job = $setup['job'];
        $recruiter = $setup['recruiter'];

        $firstCrew = User::factory()->create([
            'role' => 'crew',
        ]);

        $secondCrew = User::factory()->create([
            'role' => 'crew',
        ]);

        Application::create([
            'job_posting_id' => $job->id,
            'crew_id' => $firstCrew->id,
            'status' => 'approved',
            'reviewed_by' => $recruiter->id,
        ]);

        $pending = Application::create([
            'job_posting_id' => $job->id,
            'crew_id' => $secondCrew->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($recruiter);

        $response = $this->patchJson(
            "/api/applications/{$pending->id}/review",
            [
                'status' => 'approved',
            ]
        );

        $response->assertStatus(409);
        $this->assertDatabaseHas('applications', [
            'id' => $pending->id,
            'status' => 'pending',
            'reviewed_by' => null,
        ]);
    }

    public function test_recruiter_can_approve_when_a_position_is_available(): void
    {
        
        $setup = $this->createJobForRecruiter();

        $job = $setup['job'];
        $recruiter = $setup['recruiter'];

        $crew = User::factory()->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        $application = Application::create([
            'job_posting_id' => $job->id,
            'crew_id' => $crew->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($recruiter);

        $response = $this->patchJson(
            "/api/applications/{$application->id}/review",
            [
                'status' => 'approved',
                'notes' => 'Suitable experience'
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('application.status', 'approved');

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'approved',
            'reviewed_by' => $recruiter->id,
            'notes' => 'Suitable experience',
        ]);
    }

    public function test_recruiter_cannot_review_another_company_application(): void
    {

        $setup = $this->createJobForRecruiter();
        $job = $setup['job'];

        $otherCompany = Company::factory()->create();

        $otherRecruiter = User::factory()->create([
            'role' => 'recruiter',
            'company_id' => $otherCompany->id,
        ]);

        $crew = User::factory()->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        $application = Application::create([
            'job_posting_id' => $job->id,
            'crew_id' => $crew->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($otherRecruiter);

        $response = $this->patchJson(
            "/api/applications/{$application->id}/review",
            [
                'status' => 'approved',
            ]
        );

        $response->assertStatus(403);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'pending',
            'reviewed_by' => null,
        ]);
    }

    public function test_crew_cannot_approve_their_own_application(): void
    {
        $setup = $this->createJobForRecruiter();
        $job = $setup['job'];

        $crew = User::factory()->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        $application = Application::create([
            'job_posting_id' => $job->id,
            'crew_id' => $crew->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($crew);

        $response = $this->patchJson(
            "/api/applications/{$application->id}/review",
            ['status' => 'approved']
        );

        $response->assertStatus(403);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'pending',
            'reviewed_by' => null,
        ]);
    }

    public function test_guest_cannot_review_an_application(): void
    {
        $setup = $this->createJobForRecruiter();
        $job = $setup['job'];
    
        $crew = User::factory()->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        $application = Application::create([
            'job_posting_id' => $job->id,
            'crew_id' => $crew->id,
            'status' => 'pending',
        ]);

        $response = $this->patchJson(
            "/api/applications/{$application->id}/review",
            ['status' => 'approved']
        );

        $response->assertStatus(401);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'pending',
            'reviewed_by' => null,
        ]);
    }

    private function createJobForRecruiter(int $positions = 1): array
    {
        $company = Company::factory()->create();

        $recruiter = User::factory()->create([
            'role' => 'recruiter',
            'company_id' => $company->id,
        ]);

        $event = Event::factory()->create([
            'company_id' => $company->id,
        ]);

        $job = JobPosting::create([
            'event_id' => $event->id,
            'recruiter_id' => $recruiter->id,
            'title' => 'Registration Crew',
            'job_type' => 'registration',
            'number_of_positions' => $positions,
            'status' => 'open',
        ]);

        return [
            'job' => $job,
            'recruiter' => $recruiter,
        ];
    }

    public function test_review_rejects_an_invalid_status(): void
    {
        $setup = $this->createJobForRecruiter();
        $job = $setup['job'];
        $recruiter = $setup['recruiter'];

        $crew = User::factory()->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        $application = Application::create([
            'job_posting_id' => $job->id,
            'crew_id' => $crew->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($recruiter);

        $response = $this->patchJson(
            "/api/applications/{$application->id}/review",
            ['status' => 'accepted']
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'pending',
            'reviewed_by' => null,
        ]);
    }

    public function test_review_requires_a_status(): void
    {
        $setup = $this->createJobForRecruiter();
        $job = $setup['job'];
        $recruiter = $setup['recruiter'];

        $crew = User::factory()->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        $application = Application::create([
            'job_posting_id' => $job->id,
            'crew_id' => $crew->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($recruiter);

        $response = $this->patchJson(
            "/api/applications/{$application->id}/review",
            ['notes' => 'Experienced crew member.']
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'pending',
            'reviewed_by' => null,
            'notes' => null,
        ]);
    }

    public function test_recruiter_can_reject_when_job_is_full(): void
    {
        $setup = $this->createJobForRecruiter();
        $job = $setup['job'];
        $recruiter = $setup['recruiter'];

        $approvedCrew = User::factory()->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        $pendingCrew = User::factory()->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        $approved = Application::create([
            'job_posting_id' => $job->id,
            'crew_id' => $approvedCrew->id,
            'status' => 'approved',
            'reviewed_by' => $recruiter->id,
        ]);

        $pending = Application::create([
            'job_posting_id' => $job->id,
            'crew_id' => $pendingCrew->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($recruiter);

        $response = $this->patchJson(
            "/api/applications/{$pending->id}/review",
            [
                'status' => 'rejected',
                'notes' => 'All positions have been filled.',
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('application.status', 'rejected');

        $this->assertDatabaseHas('applications', [
            'id' => $pending->id,
            'status' => 'rejected',
            'reviewed_by' => $recruiter->id,
            'notes' => 'All positions have been filled.',
        ]);

        $this->assertDatabaseHas('applications', [
            'id' => $approved->id,
            'status' => 'approved',
        ]);
    }

    public function test_approved_application_can_be_saved_when_job_is_full(): void
    {
        $setup = $this->createJobForRecruiter();
        $job = $setup['job'];
        $recruiter = $setup['recruiter'];

        $crew = User::factory()->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        $application = Application::create([
            'job_posting_id' => $job->id,
            'crew_id' => $crew->id,
            'status' => 'approved',
            'reviewed_by' => $recruiter->id,
            'notes' => 'Initial review.',
        ]);

        Sanctum::actingAs($recruiter);

        $response = $this->patchJson(
            "/api/applications/{$application->id}/review",
            [
                'status' => 'approved',
                'notes' => 'Availability confirmed.',
            ]
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'approved',
            'reviewed_by' => $recruiter->id,
            'notes' => 'Availability confirmed.',
        ]);

        $this->assertSame(
            1,
            $job->applications()->where('status', 'approved')->count()
        );
    }

    public function test_job_capacity_cannot_be_less_than_approved_crew(): void
    {
        $setup = $this->createJobForRecruiter(2);
        $job = $setup['job'];
        $recruiter = $setup['recruiter'];

        $crewMembers = User::factory()->count(2)->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        foreach ($crewMembers as $crew) {
            Application::create([
                'job_posting_id' => $job->id,
                'crew_id' => $crew->id,
                'status' => 'approved',
                'reviewed_by' => $recruiter->id,
            ]);
        }

        Sanctum::actingAs($recruiter);

        $response = $this->patchJson(
            "/api/job-postings/{$job->id}",
            ['number_of_positions' => 1]
        );

        $response->assertStatus(409);

        $this->assertDatabaseHas('job_postings', [
            'id' => $job->id,
            'number_of_positions' => 2,
        ]);

        $this->assertSame(
            2,
            $job->applications()->where('status', 'approved')->count()
        );
    }

    public function test_job_capacity_can_equal_approved_crew_count(): void
    {
        $setup = $this->createJobForRecruiter(3);
        $job = $setup['job'];
        $recruiter = $setup['recruiter'];

        $crewMembers = User::factory()->count(2)->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        foreach ($crewMembers as $crew) {
            Application::create([
                'job_posting_id' => $job->id,
                'crew_id' => $crew->id,
                'status' => 'approved',
                'reviewed_by' => $recruiter->id,
            ]);
        }

        Sanctum::actingAs($recruiter);

        $response = $this->patchJson(
            "/api/job-postings/{$job->id}",
            ['number_of_positions' => 2]
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('job_postings', [
            'id' => $job->id,
            'number_of_positions' => 2,
        ]);

        $this->assertSame(
            2,
            $job->applications()->where('status', 'approved')->count()
        );
    }

    public function test_recruiter_cannot_view_another_company_job_applications(): void
    {
        $setup = $this->createJobForRecruiter();
        $job = $setup['job'];

        $otherCompany = Company::factory()->create();

        $otherRecruiter = User::factory()->create([
            'role' => 'recruiter',
            'company_id' => $otherCompany->id,
        ]);

        Sanctum::actingAs($otherRecruiter);

        $response = $this->getJson(
            "/api/job-postings/{$job->id}/applications"
        );

        $response->assertStatus(403);
    }

    public function test_recruiter_can_view_own_company_job_applications(): void
    {
        $setup = $this->createJobForRecruiter();
        $job = $setup['job'];
        $recruiter = $setup['recruiter'];

        $crew = User::factory()->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        $application = Application::create([
            'job_posting_id' => $job->id,
            'crew_id' => $crew->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($recruiter);

        $response = $this->getJson(
            "/api/job-postings/{$job->id}/applications"
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'applications');
        $response->assertJsonPath('applications.0.id', $application->id);
        $response->assertJsonPath('applications.0.crew.id', $crew->id);
        $response->assertJsonPath('pagination.total', 1);
    }

    public function test_job_applications_only_returns_requested_job(): void
    {
        $setup = $this->createJobForRecruiter();
        $job = $setup['job'];
        $recruiter = $setup['recruiter'];

        $otherJob = JobPosting::create([
            'event_id' => $job->event_id,
            'recruiter_id' => $recruiter->id,
            'title' => 'Another Job',
            'job_type' => 'registration',
            'number_of_positions' => 2,
            'status' => 'open',
        ]);

        $crew = User::factory()->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        $application = Application::create([
            'job_posting_id' => $job->id,
            'crew_id' => $crew->id,
            'status' => 'pending',
        ]);

        Application::create([
            'job_posting_id' => $otherJob->id,
            'crew_id' => $crew->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($recruiter);

        $response = $this->getJson(
            "/api/job-postings/{$job->id}/applications"
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'applications');
        $response->assertJsonPath('applications.0.id', $application->id);
        $response->assertJsonPath('pagination.total', 1);
    }

    public function test_job_applications_can_be_filtered_by_status(): void
    {
        $setup = $this->createJobForRecruiter();
        $job = $setup['job'];
        $recruiter = $setup['recruiter'];

        $expectedIds = [];

        foreach (['pending', 'approved', 'rejected'] as $status) {
            $crew = User::factory()->create([
                'role' => 'crew',
                'company_id' => null,
            ]);

            $application = Application::create([
                'job_posting_id' => $job->id,
                'crew_id' => $crew->id,
                'status' => $status,
                'reviewed_by' => $status === 'pending' ? null : $recruiter->id,
            ]);

            $expectedIds[$status] = $application->id;
        }

        Sanctum::actingAs($recruiter);

        foreach ($expectedIds as $status => $id) {
            $this->getJson(
                "/api/job-postings/{$job->id}/applications?status={$status}"
            )
                ->assertOk()
                ->assertJsonCount(1, 'applications')
                ->assertJsonPath('applications.0.id', $id)
                ->assertJsonPath('applications.0.status', $status)
                ->assertJsonPath('pagination.total', 1);
        }
    }

    public function test_crew_cannot_view_job_applications(): void
    {
        $setup = $this->createJobForRecruiter();
        $job = $setup['job'];
        $crew = User::factory()->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        Application::create([
            'job_posting_id' => $job->id,
            'crew_id' => $crew->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($crew);

        $this->getJson("/api/job-postings/{$job->id}/applications")
            ->assertForbidden();
    }

    public function test_guest_cannot_view_job_applications(): void
    {
        $setup = $this->createJobForRecruiter();
        $job = $setup['job'];

        $this->getJson("/api/job-postings/{$job->id}/applications")
            ->assertUnauthorized();
    }

    public function test_recruiter_without_company_cannot_view_job_applications(): void
    {
        $setup = $this->createJobForRecruiter();
        $job = $setup['job'];
        $recruiter = User::factory()->create([
            'role' => 'recruiter',
            'company_id' => null,
        ]);

        Sanctum::actingAs($recruiter);

        $this->getJson("/api/job-postings/{$job->id}/applications")
            ->assertForbidden();
    }

    public function test_job_without_applications_returns_empty_list(): void
    {
        $setup = $this->createJobForRecruiter();
        $job = $setup['job'];
        Sanctum::actingAs($setup['recruiter']);

        $this->getJson("/api/job-postings/{$job->id}/applications")
            ->assertOk()
            ->assertJsonPath('applications', [])
            ->assertJsonPath('pagination.total', 0);
    }

    public function test_job_applications_are_paginated(): void
    {
        $setup = $this->createJobForRecruiter();
        $job = $setup['job'];
        $crews = User::factory()->count(2)->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        $applications = [];
        foreach ($crews as $crew) {
            $applications[] = Application::create([
                'job_posting_id' => $job->id,
                'crew_id' => $crew->id,
                'status' => 'pending',
            ]);
        }

        Sanctum::actingAs($setup['recruiter']);

        $this->getJson("/api/job-postings/{$job->id}/applications?per_page=1&page=1")
            ->assertOk()
            ->assertJsonCount(1, 'applications')
            ->assertJsonPath('applications.0.id', $applications[1]->id)
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('pagination.last_page', 2)
            ->assertJsonPath('pagination.per_page', 1)
            ->assertJsonPath('pagination.total', 2);

        $this->getJson("/api/job-postings/{$job->id}/applications?per_page=1&page=2")
            ->assertOk()
            ->assertJsonCount(1, 'applications')
            ->assertJsonPath('applications.0.id', $applications[0]->id)
            ->assertJsonPath('pagination.current_page', 2);
    }

    public function test_job_applications_reject_invalid_pagination(): void
    {
        $setup = $this->createJobForRecruiter();
        $job = $setup['job'];
        Sanctum::actingAs($setup['recruiter']);

        foreach ([0, 51, 'invalid'] as $perPage) {
            $this->getJson("/api/job-postings/{$job->id}/applications?per_page={$perPage}")
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['per_page']);
        }

        $this->getJson("/api/job-postings/{$job->id}/applications?page=0")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['page']);
    }

    public function test_job_applications_reject_invalid_status(): void
    {
        $setup = $this->createJobForRecruiter();
        $job = $setup['job'];

        Sanctum::actingAs($setup['recruiter']);

        $this->getJson(
            "/api/job-postings/{$job->id}/applications?status=invalid"
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_crew_can_withdraw_own_pending_application(): void
    {
        $setup = $this->createJobForRecruiter();
        $job = $setup['job'];

        $crew = User::factory()->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        $application = Application::create([
            'job_posting_id' => $job->id,
            'crew_id' => $crew->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($crew);

        $response = $this->patchJson(
            "/api/applications/{$application->id}/withdraw"
        );

        $response->assertOk();
        $response->assertJsonPath('application.status', 'withdrawn');

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'crew_id' => $crew->id,
            'status' => 'withdrawn',
            'reviewed_by' => null,
        ]);
    }

    public function test_crew_cannot_withdraw_another_crew_application(): void
    {
        $setup = $this->createJobForRecruiter();
        $job = $setup['job'];

        $owner = User::factory()->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        $otherCrew = User::factory()->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        $application = Application::create([
            'job_posting_id' => $job->id,
            'crew_id' => $owner->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($otherCrew);

        $response = $this->patchJson(
            "/api/applications/{$application->id}/withdraw"
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'crew_id' => $owner->id,
            'status' => 'pending',
            'reviewed_by' => null,
        ]);
    }

    public function test_crew_cannot_withdraw_non_pending_application(): void
    {
        $setup = $this->createJobForRecruiter();
        $job = $setup['job'];
        $recruiter = $setup['recruiter'];

        foreach (['approved', 'rejected', 'withdrawn'] as $status) {
            $crew = User::factory()->create([
                'role' => 'crew',
                'company_id' => null,
            ]);

            $reviewerId = $status === 'withdrawn'
                ? null
                : $recruiter->id;

            $application = Application::create([
                'job_posting_id' => $job->id,
                'crew_id' => $crew->id,
                'status' => $status,
                'reviewed_by' => $reviewerId,
            ]);

            Sanctum::actingAs($crew);

            $this->patchJson(
                "/api/applications/{$application->id}/withdraw"
            )->assertStatus(409);

            $this->assertDatabaseHas('applications', [
                'id' => $application->id,
                'status' => $status,
                'reviewed_by' => $reviewerId,
            ]);
        }
    }
    public function test_recruiter_cannot_review_withdrawn_application(): void
    {
        $setup = $this->createJobForRecruiter();
        $job = $setup['job'];
        $recruiter = $setup['recruiter'];

        $crew = User::factory()->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        $application = Application::create([
            'job_posting_id' => $job->id,
            'crew_id' => $crew->id,
            'status' => 'withdrawn',
        ]);

        Sanctum::actingAs($recruiter);

        foreach (['approved', 'rejected'] as $status) {
            $response = $this->patchJson(
                "/api/applications/{$application->id}/review",
                [
                    'status' => $status,
                    'notes' => 'Trying to review after withdrawal.',
                ]
            );

            $response->assertStatus(409);

            $this->assertDatabaseHas('applications', [
                'id' => $application->id,
                'status' => 'withdrawn',
                'reviewed_by' => null,
                'notes' => null,
            ]);
        }
    }

    public function test_my_applications_only_returns_logged_in_crew_applications(): void
    {
        $setup = $this->createJobForRecruiter();
        $job = $setup['job'];

        $crew = User::factory()->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        $otherCrew = User::factory()->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        $ownApplication = Application::create([
            'job_posting_id' => $job->id,
            'crew_id' => $crew->id,
            'status' => 'pending',
        ]);

        Application::create([
            'job_posting_id' => $job->id,
            'crew_id' => $otherCrew->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($crew);

        $this->getJson('/api/my-applications')
            ->assertOk()
            ->assertJsonCount(1, 'applications')
            ->assertJsonPath('applications.0.id', $ownApplication->id)
            ->assertJsonPath('applications.0.crew.id', $crew->id)
            ->assertJsonPath('pagination.total', 1);
    }
    
    public function test_recruiter_cannot_access_my_applications(): void
    {
        $setup = $this->createJobForRecruiter();
        $recruiter = $setup['recruiter'];

        Sanctum::actingAs($recruiter);

        $this->getJson('/api/my-applications')
            ->assertForbidden();
    }

    public function test_my_applications_filters_by_status_and_owner(): void
    {
        $firstSetup = $this->createJobForRecruiter();
        $secondSetup = $this->createJobForRecruiter();

        $crew = User::factory()->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        $otherCrew = User::factory()->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        $approved = Application::create([
            'job_posting_id' => $firstSetup['job']->id,
            'crew_id' => $crew->id,
            'status' => 'approved',
            'reviewed_by' => $firstSetup['recruiter']->id,
        ]);

        Application::create([
            'job_posting_id' => $secondSetup['job']->id,
            'crew_id' => $crew->id,
            'status' => 'pending',
        ]);

        Application::create([
            'job_posting_id' => $secondSetup['job']->id,
            'crew_id' => $otherCrew->id,
            'status' => 'approved',
            'reviewed_by' => $secondSetup['recruiter']->id,
        ]);

        Sanctum::actingAs($crew);

        $this->getJson('/api/my-applications?status=approved')
            ->assertOk()
            ->assertJsonCount(1, 'applications')
            ->assertJsonPath('applications.0.id', $approved->id)
            ->assertJsonPath('applications.0.status', 'approved')
            ->assertJsonPath('applications.0.crew.id', $crew->id)
            ->assertJsonPath('pagination.total', 1);
    }

    public function test_my_applications_rejects_invalid_status(): void
    {
        $crew = User::factory()->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        Sanctum::actingAs($crew);

        $this->getJson('/api/my-applications?status=accepted')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_my_applications_are_paginated(): void
    {
        $crew = User::factory()->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        $applications = [];

        foreach (range(1, 2) as $number) {
            $setup = $this->createJobForRecruiter();

            $applications[] = Application::create([
                'job_posting_id' => $setup['job']->id,
                'crew_id' => $crew->id,
                'status' => 'pending',
            ]);
        }

        Sanctum::actingAs($crew);

        $this->getJson('/api/my-applications?per_page=1&page=1')
            ->assertOk()
            ->assertJsonCount(1, 'applications')
            ->assertJsonPath('applications.0.id', $applications[1]->id)
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('pagination.last_page', 2)
            ->assertJsonPath('pagination.per_page', 1)
            ->assertJsonPath('pagination.total', 2);

        $this->getJson('/api/my-applications?per_page=1&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'applications')
            ->assertJsonPath('applications.0.id', $applications[0]->id)
            ->assertJsonPath('pagination.current_page', 2)
            ->assertJsonPath('pagination.total', 2);
    }

    public function test_job_search_only_returns_matching_open_jobs(): void
    {
        $matching = $this->createJobForRecruiter()['job'];
        $matching->update([
            'title' => 'Registration Crew',
            'status' => 'open',
        ]);

        $closed = $this->createJobForRecruiter()['job'];
        $closed->update([
            'title' => 'Registration Supervisor',
            'status' => 'closed',
        ]);

        $unrelated = $this->createJobForRecruiter()['job'];
        $unrelated->update([
            'title' => 'Stage Setup Crew',
            'status' => 'open',
        ]);

        $crew = User::factory()->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        Sanctum::actingAs($crew);

        $this->getJson('/api/job-postings?search=Registration')
            ->assertOk()
            ->assertJsonCount(1, 'job_postings')
            ->assertJsonPath('job_postings.0.id', $matching->id)
            ->assertJsonPath('job_postings.0.status', 'open')
            ->assertJsonPath('pagination.total', 1);
    }

    public function test_job_list_without_search_only_returns_open_jobs(): void
    {
        $openJob = $this->createJobForRecruiter()['job'];

        $closedJob = $this->createJobForRecruiter()['job'];
        $closedJob->update(['status' => 'closed']);

        $cancelledJob = $this->createJobForRecruiter()['job'];
        $cancelledJob->update(['status' => 'cancelled']);

        $crew = User::factory()->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        Sanctum::actingAs($crew);

        $this->getJson('/api/job-postings')
            ->assertOk()
            ->assertJsonCount(1, 'job_postings')
            ->assertJsonPath('job_postings.0.id', $openJob->id)
            ->assertJsonPath('job_postings.0.status', 'open')
            ->assertJsonPath('pagination.per_page', 10)
            ->assertJsonPath('pagination.total', 1);
    }

    public function test_job_list_combines_search_type_and_open_status(): void
    {
        $cases = [
            ['title' => 'Capacity Crew', 'job_type' => 'registration', 'status' => 'open'],
            ['title' => 'Capacity Supervisor', 'job_type' => 'supervisor', 'status' => 'open'],
            ['title' => 'Welcome Crew', 'job_type' => 'registration', 'status' => 'open'],
            ['title' => 'Capacity Closed', 'job_type' => 'registration', 'status' => 'closed'],
        ];

        $jobs = [];

        foreach ($cases as $attributes) {
            $job = $this->createJobForRecruiter()['job'];
            $job->update($attributes);
            $jobs[] = $job;
        }

        $crew = User::factory()->create([
            'role' => 'crew',
            'company_id' => null,
        ]);

        Sanctum::actingAs($crew);

        $this->getJson(
            '/api/job-postings?search=Capacity&job_type=registration'
        )
            ->assertOk()
            ->assertJsonCount(1, 'job_postings')
            ->assertJsonPath('job_postings.0.id', $jobs[0]->id)
            ->assertJsonPath('job_postings.0.job_type', 'registration')
            ->assertJsonPath('job_postings.0.status', 'open')
            ->assertJsonPath('pagination.total', 1);
    }
}