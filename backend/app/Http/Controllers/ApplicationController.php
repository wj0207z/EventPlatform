<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Application;
use App\Models\JobPosting;

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

    public function store(Request $request, JobPosting $jobPosting)
    {
        $user = $request->user();

        if (!user || $user->role !== 'crew'){
            return response()->json([
                'message' => 'Only crew can apply for jobs'
            ], 403);
        }

        if ($jobPosting->status !== 'open'){
            return response()->json([
                'message' => 'The job is no longer accepting applications'
            ], 422);
        }

        $alreadyApplied = Application::where([
            'job_posting_id' => $jobPosting->id,
            'crew_id' => $user->id,
        ])->exists();

        if ($alreadyApplied){
            return response()->json([
                'message' => 'You have already applied for this job'
            ], 409);
        }

        $application = Application::create([
            'job_posting_id' => $jobPosting->id,
            'crew_id' => $user->id,
            'status' => 'pending'
        ]);

        return response()->json([
            'message' => 'Application submitted successfully',
            'application' => $application->load([
                '$jobPosting',
                'crew',
            ]),
        ], 201);
    }

    public function myApplications(Request $request)
    {
        $applications = Application::query()
            ->where('crew_id', $request->user()->id)
            ->with([
                'JobPosting.event',
                'reviewer',
            ])
            ->latest()
            ->get();

        return response()->json([
            'applications' => $applications,
        ]);
    }

    public function recruiterApplication(Request $request)
    {
        $user = $request->user();

        if($user->role !== 'recruiter') {
            return response()->json([
                'message' => 'Only recruiters can view applications',
            ], 403);
        }

        $applications = Application::query() //searching from application table
            //find applications where the application's job post belongs to an event owned by the recruiter's company
            //firstly inspect the related event records
            //go through application to jobPosting to event
            //the query is laravel function
            //use $user give access to logged in user
            ->whereHas('jobPosting.event', function ($query) use ($user){ //this is anonymouns function or closure
                $query->where('company_id', $user->company_id)
            })
            ->with([
                'crew.crewProfile',
                'jobPosting.event',
                'reviewer'
            ])
            ->latest()
            ->get();

        return response()->json([
            'applications' => $applications
        ]);
    }
}
