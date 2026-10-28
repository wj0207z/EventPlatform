<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Http\Resources\EventResource;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'recruiter' || !$user->company_id) {
            return response()->json([
                'message' => 'Only recruiters can view company event'
            ], 403);
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'required', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'required', 'integer', 'min:1'],
        ]);

        $search = trim($validated['search'] ?? '');

        $events = Event::with('client') //load each event and its related client
            ->where('company_id', $user->company_id)
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', "%" . $search . "%");
            })
            ->with(['client'])
            ->orderBy('event_date')
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 10);

        return response()->json([
            'events' => EventResource::collection($events->items()),
            'pagination' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    public function store(StoreEventRequest $request)
    {
        $validated = $request->validated();

        $event = Event::create([
            'client_id' => $validated['client_id'],
            'company_id' => $request->user()->company_id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'event_date' => $validated['event_date'],
            'location' => $validated['location'],
            'status' => 'planning',
        ]);

        return response()->json([
            'message' => 'Event created successfully.',
            'event' => new EventResource($event->load('client')),
        ], 201);
    }

    public function show(Event $event)
    {
        $this->authorize('view', $event);

        $event->load('client');

        return response()->json([
            'event' => new EventResource($event),
        ]);
    }

    public function update(UpdateEventRequest $request, Event $event)
    {
        $validated = $request->safe()->only([
            'name',
            'description',
            'event_date',
            'location',
        ]);

        $event->update($validated);

        return response()->json([
            'message' => 'Event updated successfully.',
            'event' => new EventResource($event->load('client')),
        ], 200);
    }
}
