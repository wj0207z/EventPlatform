<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CrewProfileController extends Controller
{
    public function update(Request $requst)
    {
        $user = $request->user();

        if (!$user || $user->role !== 'crew'){
            return response()->json([
                'message' => 'Only crew can access crew profiles'
            ], 403);
        }

        $validated = $request->valdidated([
            'bio' => ['nullable', 'string', 'max:2000'],
            'skills' => ['nullable', 'string', 'max:2000'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:80'],
            'availability' => [
                'required',
                'in:available,unavailable'
                ],
        ]);

        $profile = CrewProfile::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );
        
        return response()->json([
            'message' => 'Crew profile updated successfully',
            'profile' => $profile->load('user'),
        ]);
    }
}
