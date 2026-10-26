<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobPostingRequest;
use App\Http\Resources\JobPostingResource;
use App\Models\JobPosting;
use App\Http\Requests\UpdateJobPostingRequest;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class JobPostingController extends Controller
{
    public function index(Request $request)
    {

        $validated = $request->validate([
            'search' => ['nullable','string','max:255'],
            'per_page' => ['sometimes','required','integer','min:1','max:50'],
            'page' => ['sometimes','required','integer','min:1'],
        ]);

        $search = trim($validated['search'] ?? '');

        $jobs = JobPosting::query()
            ->where('status', 'open')
            ->when($search !== '', function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%');
            })
            ->with(['event', 'recruiter'])
            ->latest()
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 10);

        return response()->json([
            'job_postings' => JobPostingResource::collection($jobs->items()),

            'pagination' => [
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'per_page' => $jobs->perPage(),
                'total' => $jobs->total(),
            ],
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

        $jobPosting = JobPosting::create([
            'event_id' => $event->id,
            'recruiter_id' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
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

        $validated = $request->validated();

        return DB::transaction(function () use
        ($jobPosting, $validated) {
            $jobPosting = $jobPosting::whereKey($jobPosting->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (array_key_exists('number_of_positions', $validated)) {
                $approvedCount = $jobPosting->applications()
                    ->where('status', 'approved')
                    ->count();

                if ((int) $validated['number_of_positions'] < $approvedCount) {
                    return response()->json([
                        'message' => 'Cannot set number of positions less than the number of approved applications',
                    ], 409);
                }
            }

            $jobPosting->update($validated);

            return response()->json([
                'message' => 'Job Post updated successfully',
                'job_posting' => new JobPostingResource(
                    $jobPosting->load([
                        'event',
                        'recruiter',
                    ])
                ),
            ], 200);
        });
    }

    public function destroy(JobPosting $jobPosting)
    {
        $this->authorize('delete', $jobPosting);

        if ($jobPosting->applications()->exists()){
            return response()->json([
                'message' => 'This job cannot be deleted because applications already exist',
            ], 409);
        }

        $jobPosting->delete();

        return response()->json([
            'message' => 'Job Post deleted successfully',
        ], 200);
    }

    public function recruiterJobs(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'recruiter' || !$user->company_id){
            return response()->json([
                'message' => 'Only recruiters with a company can access this list'
            ], 403);
        }

        $validated = $request->validate([
            'status' => ['sometimes','required','in:open,closed, cancelled'],
            'search' => ['sometimes','string','max:255'],
            'per_page' => ['sometimes','integer','min:1', 'max:50'],
            'page' => ['sometimes','integer','min:1'],
        ]);

        $query = JobPosting::query()
            ->whereHas('event', function ($query) use ($user) {
                $query->where('company_id', $user->company_id);
            })
            ->with(['event', 'recruiter'])
            ->withCount([
                'applications',
                'applications as pending_applications_count' =>
                function ($query) {
                    $query->where('status', 'pending');
                },
                'applications as approved_applications_count' =>
                function ($query) {
                    $query->where('status', 'approved');
                },
            ]);

            if (isset($validated['status'])) {
                $query->where('status', $validated['status']);
            }

            $search = trim($validated['search'] ?? '');

            if($search !== '') {
                $query->where('title', 'like', '%' . $search . '%');
            }

            $jobs = $query->latest()->orderByDesc('id')->paginate((int) ($validated['per_page'] ?? 10));

            return response()->json([
                'job_postings' => JobPostingResource::collection($jobs),
                'pagination' => [
                    'current_page' => $jobs->currentPage(),
                    'last_page' => $jobs->lastPage(),
                    'per_page' => $jobs->perPage(),
                    'total' => $jobs->total(),
                ],
            ]);
    }
}
