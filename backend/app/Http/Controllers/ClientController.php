<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'recruiter' || !$user->company_id) {
            return response()->json([
                'message' => 'Only recruiters with a company can view clients.',
            ], 403);
        }

        $company = Company::findOrFail($user->company_id);

        $clients = $company->clients()
            ->orderBy('clients.name')
            ->get()
            ->map(function ($client) {
                return [
                    'id' => $client->id,
                    'name' => $client->name,
                ];
            });

        return response()->json([
            'clients' => $clients,
        ]);
    }
}