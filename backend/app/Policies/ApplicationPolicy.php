<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    //receives two objects and return boolean
    //check if user is recruiter
    public function review(
        User $user,
        Application $application
    ) : bool {
        if ($user->role !== 'recruiter') {
            return false;
        }

        //check the application's company
        $jobCompanyId = $application
            ->jobPosting
            ->event
            ->company_id;

        //upgrade security to prevent both null and have access
        //if either one or both null will return false
        if (!$user->company_id || !$jobCompanyId) {
            return false;
        }

        //compare recruiter's company id and event's company id
        //if true them allow to review
        return (int) $user->company_id === (int) $jobCompanyId;
    }
}
