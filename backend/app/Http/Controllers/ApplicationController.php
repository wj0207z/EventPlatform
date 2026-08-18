<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function review(Request $request, Application $application)
    {
        //verify if the logged in user is recruiter 
        $user = $request->user()

        if (!user || $user->role !== 'recruiter'){
            return response()->json([
                'message' => 'Only recruiters can review application',
            ], 403);
        }

        //checks if the recruiter belongs to the same company as the job
        //go through the relationships and comparing the ids
        //gets the company id that owns the event related to the application
        $jobCompanyId = $application
            ->jobPosting
            ->event
            ->company_id;
        
        //check if the logged in recruiter's company id against the event company id
        if ($user->company_id !== $jobCompanyId){
            return response()->json([
                'message' => 'You cannot review applications for this company',
            ], 403);
        }

        //final process of reviewing application
        //status must exist and either approved or rejected
        //notes is optional
        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        //update the existing application in MySQL
        //before is status pending and notes null
        $application->update([
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null, // use notes if exist otherwise null
            'reviewed_by' => $user->id,
        ]);

        return response()->json([
            'message' => 'Application reviewed successfully',
            'application' => $application->load([
                'crew',
                'jobPosting',
                'reviewer',
            ]),
        ]);
    }
}
