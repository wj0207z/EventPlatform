<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'recruiter'){
            return response()->json([
                'message' => 'Only recruiters can view company event'
            ], 403);
        }

        $events = Event::with('client') //load each event and its related client
            ->where('company_id', $user->company_id)
            ->orderBy('event_date')
            ->get();

        return response()->json([
            'events' => $events,
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
            'event' => [
                'id' => $event->id,
                'client_id' => $event->client_id,
                'company_id' => $event->company_id,
                'name' => $event->name,
                'description' => $event->description,
                'event_date' => $event->event_date->format('Y-m-d'),
                'location' => $event->location,
                'status' => $event->status,
            ],
        ], 201);
    }

    public function show(Event $event)
    {
        $this->authorize('view', $event);

        $event->load('client');

        return response()->json([
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'description' => $event->description,
                'event_date' => $event->event_date->format('Y-m-d'),
                'location' => $event->location,
                'status' => $event->status,
                'client' => [
                    'id' => $event->client->id,
                    'name' => $event->client->name,
                ],
            ],
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
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'description' => $event->description,
                'event_date' => $event->event_date->format('Y-m-d'),
                'location' => $event->location,
                'status' => $event->status,
            ],
        ], 200);
    }
}
