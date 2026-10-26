<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Application;
use App\Models\JobPosting;
use App\Http\Resources\ApplicationResource;
use Illuminate\Support\Facades\DB;

class ApplicationController extends Controller
{
    public function review(Request $request, Application $application)
    {
        $this->authorize('review', $application);

        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return DB::transaction(function () use ($request, $application, $validated) {
            $job = JobPosting::whereKey($application->job_posting_id)
                ->lockForUpdate()
                ->firstOrFail();

            $application = Application::whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($application->status === 'withdrawn') {
                return response()->json([
                    'message' => 'Withdrawn applications cannot be reviewed.',
                ], 409);
            }

            if ($validated['status'] === 'approved') {
                $approvedCount = $job->applications()
                    ->where('status', 'approved')
                    ->where('id', '!=', $application->id)
                    ->count();

                if ($approvedCount >= $job->number_of_positions) {
                    return response()->json([
                        'message' => 'This job already has enough approved crew.',
                    ], 409);
                }
            }

            $application->update([
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
                'reviewed_by' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Application reviewed successfully',
                'application' => new ApplicationResource(
                    $application->load([
                        'crew.crewProfile',
                        'jobPosting.event',
                        'reviewer',
                    ])
                ),
            ], 200);
        });
    }

    public function store(Request $request, JobPosting $jobPosting)
    {
        $user = $request->user();

        if ($user->role !== 'crew'){
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
            'application' => new ApplicationResource(
                $application->load([
                            'crew.crewProfile',
                            'jobPosting.event',
                            'reviewer',
                ]),
            ),
        ], 201);
    }

    public function myApplications(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'crew') {
            return response()->json([
                'message' => 'Only crew can view their application history.',
            ], 403);
        }

        $validated = $request->validate([
            'status' => [
                'sometimes', 'required',
                'in:pending,approved,rejected,withdrawn',
            ],
            'per_page' => ['sometimes', 'required', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'required', 'integer', 'min:1'],
        ]);

        $applications = Application::query()
            ->where('crew_id', $user->id)
            ->when(
                $validated['status'] ?? null,
                function ($query, $status) {
                    $query->where('status', $status);
                }
            )
            ->with(['crew.crewProfile', 'jobPosting.event', 'reviewer'])
            ->latest()
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 10);

        return response()->json([
            'applications' => ApplicationResource::collection(
                $applications->items()
            ),
            'pagination' => [
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
            ],
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

        $validated = $request->validate([
            'status' => [
                'nullable',
                'in:pending,approved,rejected',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:50'
            ]
        ]);

        $perPage = $validated['per_page'] ?? 10;

        $applications = Application::query() //searching from application table
            //find applications where the application's job post belongs to an event owned by the recruiter's company
            //firstly inspect the related event records
            //go through application to jobPosting to event
            //the query is laravel function
            //use $user give access to logged in user
            ->whereHas('jobPosting.event', function ($query) use ($user){ //this is anonymouns function or closure
                $query->where('company_id', $user->company_id);
            })
            ->when(
                $validated['status'] ?? null,
                function ($query, $status) {
                    $query->where('status', $status);
                }
            )
            ->with([
                'crew.crewProfile',
                'jobPosting.event',
                'reviewer'
            ])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'applications' => ApplicationResource::collection(
                $applications->items()
            ),

            'pagination' => [
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
            ],
        ]);
    }

    public function jobApplications(Request $request, JobPosting $jobPosting)
    {
        $this->authorize('viewApplications', $jobPosting);

        $validated = $request->validate([
            'per_page' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'max:50'
            ],

            'page' => [
                'sometimes',
                'required',
                'integer',
                'min:1'
            ],

            'status' => [
                'sometimes',
                'required',
                'in:pending,approved,rejected'
            ]
        ]);

        $applications = $jobPosting->applications()
            ->when(
                $validated['status'] ?? null,
                function ($query, $status) {
                    $query->where('status', $status);
                }
            )
            ->with([
                'crew.crewProfile',
                'jobPosting.event',
                'reviewer'
            ])
            ->latest()
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 10);

        return response()->json([
            'applications' => ApplicationResource::collection(
                $applications->items()
            ),
            'pagination' => [
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
            ],
        ]);
    }

    public function withdraw(Application $application)
    {
        $this->authorize('withdraw', $application);

        return DB::transaction(function () use ($application) {
            $application = Application::whereKey($application->id)
            ->lockForUpdate()
            ->firstOrFail();

            if ($application->status !== 'pending') {
                return response()->json([
                    'message' => 'Only pending applications can be withdrawn',
                ], 409);
            }

        $application->update([
            'status' => 'withdrawn',
        ]);

        return response()->json([
            'message' => 'Application withdrawn successfully',
            'application' => new ApplicationResource(
                $application->load([
                    'crew.crewProfile',
                    'jobPosting.event',
                    'reviewer',
                ])
            ),
        ], 200);
        });
    }
}