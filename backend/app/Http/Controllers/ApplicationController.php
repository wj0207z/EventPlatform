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
        $this->authorize('review', $application);

        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $application->update([
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'reviewed_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Application reviewed successfully',
            'application' => $application->load([
                'crew.crewProfile',
                'jobPosting.event',
                'reviewer',
            ]),
        ]);
    }

    public function store(Request $request, JobPosting $jobPosting)
    {
        $user = $request->user();

        if (!$user || $user->role !== 'crew'){
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
                'jobPosting',
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

    public function recruiterApplications(Request $request)
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
                $query->where('company_id', $user->company_id);
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
