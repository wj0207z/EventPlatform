<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use App\Http\Requests\StoreClientRequest;
use App\Models\Client;
use Illuminate\Support\Facades\DB;

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

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'required', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'required', 'integer', 'min:1'],
        ]);

        $company = Company::findOrFail($user->company_id);

        $search = trim($validated['search'] ?? '');

        $clients = $company->clients()
            ->when($search !== '', function ($query) use ($search) {
                $query->where('clients.name', 'like', "%" . $search . "%");
            })
            ->orderBy('clients.name')
            ->orderBy('clients.id')
            ->paginate($validated['per_page'] ?? 10);
            
        $items = $clients->getCollection()->map(function ($client) {
            return [
                'id' => $client->id,
                'name' => $client->name,
            ];
        });

        return response()->json([
            'clients' => $items,
            'pagination' => [
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
                'per_page' => $clients->perPage(),
                'total' => $clients->total(),
            ],
        ]);
    }

    public function store(StoreClientRequest $request)
    {
        $validated = $request->validated();

        $company = Company::findOrFail($request->user()->company_id);

        $client = DB::transaction(function () use ($validated, $company) {
            $client = Client::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
            ]);

            $company->clients()->attach($client->id);

            return $client;
        });

        return response()->json([
            'message' => 'Client created successfully.',
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
            ],
        ], 201);
    }
}