<?php

namespace App\Policies;

use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class JobPostingPolicy
{
    public function update(User $user, JobPosting $jobPosting
    ): bool {
        $jobCompanyId = $jobPosting
            ->event
            ?->company_id;

        //security check
        return $user->role === 'recruiter'
            && $user->company_id
            && $jobCompanyId
            && (int) $user->company_id === (int) $jobCompanyId;
    }

    public function delete(
        User $user,
        JobPosting $jobPosting
    ): bool {
        $jobCompanyId = $jobPosting
        ->event
        ?->company_id;

        return $user->role === 'recruiter'
            && $user->company_id
            && $jobCompanyId
            && (int) $user->company_id === (int) $jobCompanyId;
    }
    
    public function viewApplications(
        User $user,
        JobPosting $jobPosting
    ): bool {
        if ($user->role !== 'recruiter') {
            return false;
        }

        $jobCompanyId = $jobPosting->event?->company_id;

        if (!$user->company_id || !$jobCompanyId) {
            return false;
        }

        return (int) $user->company_id === (int) $jobCompanyId;
    }
}
