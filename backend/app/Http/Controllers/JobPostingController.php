<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use Illuminate\Http\Request;

class JobPostingController extends Controller
{
    public function index()
    {
        $jobs = JobPosting::with([
            'event',
            'recruiter',
        ])
        ->where('status', 'open')
        ->latest()
        ->get();

        return response()->json([
            'job_postings' => $jobs,
        ]);
    }
    
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'recruiter'){
            return response()->json([
                'message' => 'Only recruiters can create job post'
            ], 403);
        }

        $validated = $request->validate([
            'event_id' => [
                'required',
                'integer',
                'exist:events,id', //checks the event table, specifically the id column
            ],
            'title' => [
                'required',
                'string',
                'max:255'
            ],
            'description' => [
                'nullable',
                'string'
            ],
            'crew_type' => [
                'required',
                'in:normal,supervisor',
            ],
            'pay_rate' => [
                'nullable',
                'numeric',
                'min:0'
            ],
        ]);

        $event = Event::findOrFail($validated['event_id']);

        if ((int) $event->company_id !== (int) $user->company_id){
            return response()->json([
                'message' => 'You cannot create a job for this company event'
            ], 403);
        }

        $jobPosting = jobPosting::create([
            'event_id' => $event->id,
            'recruiter_id' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'crew_type' => $validated['crew_type'],
            'number_of_positions' => $validated['number_of_positions'],
            'pay_rate' => $validated['pay_rate'] ?? null,
            'status' => 'open'
        ]);

        return response()->json([
            'message' => 'Job Post created successfully',
            'job_posting' => $jobPosting->load([
                'event',
                'recruiter',
            ]),
        ], 201);
    }
}
