<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobPostingRequest;
use App\Http\Resources\JobPostingResource;
use App\Models\JobPosting;
use Illuminate\Http\Request;
use App\Http\Requests\UpdateJobPostingRequest;
use App\Http\Resources\JobPostingResource;

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
            'job_postings' => JobPostingResource::collection($jobs),
        ]);
    }

    public function store(StoreJobPostingRequest $request)
    {
        $user = $request->user();

        $validated = $request->validated();

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
            'job_type' => $validated['job_type'],
            'number_of_positions' => $validated['number_of_positions'],
            'pay_rate' => $validated['pay_rate'] ?? null,
            'status' => 'open'
        ]);

        return response()->json([
            'message' => 'Job Post created successfully',
            'job_posting' => new JobPostingResource(
                $jobPosting->load([
                    'event',
                    'recruiter',
                ]),
            ),
        ], 201);
    }

    public function show(JobPosting $jobPosting)
    {
        $jobPosting->load([
            'event',
            'recruiter'
        ]);

        return response()->json([
            'job_posting' => new JobPostingResource($jobPosting),
        ]);
    }

    public function update(
        UpdateJobPostingRequest $request,
        JobPosting $jobPosting
    ){
        $this ->authorize('update', $jobPosting);

        $jobPosting->update($request->validated());

        return response()->json([
            'message' => 'Job Post updated successfully',
            'job_posting' => new JobPostingResource(
                $jobPosting->load([
                    'event',
                    'recruiter',
                ])
            ),
        ], 200);
    }
}
