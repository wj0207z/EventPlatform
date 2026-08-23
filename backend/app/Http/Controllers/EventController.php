<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;

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
}
