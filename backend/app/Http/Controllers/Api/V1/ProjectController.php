<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\Objects\Point;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Project::with(['client', 'artisan', 'trade']);

        // Filter by user role
        if ($user->role === 'client') {
            $query->where('client_id', $user->id);
        } elseif ($user->role === 'artisan') {
            $query->where('artisan_id', $user->id)
                ->orWhere('status', 'awaiting_quotes');
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $projects = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($projects);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'trade_id' => 'required|exists:trades,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'address' => 'nullable|string',
            'expected_completion_date' => 'nullable|date|after:today',
            'budget_min' => 'nullable|numeric|min:0',
            'budget_max' => 'nullable|numeric|min:0',
        ]);

        $project = Project::create([
            'client_id' => $request->user()->id,
            'trade_id' => $validated['trade_id'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'location' => new Point($validated['latitude'], $validated['longitude']),
            'address' => $validated['address'] ?? null,
            'expected_completion_date' => $validated['expected_completion_date'] ?? null,
            'budget_min' => $validated['budget_min'] ?? null,
            'budget_max' => $validated['budget_max'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json($project->load(['client', 'trade']), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $project = Project::with([
            'client',
            'artisan',
            'trade',
            'quotes.artisan',
            'escrowWallet',
            'milestones',
            'review'
        ])->findOrFail($id);

        return response()->json($project);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $project = Project::findOrFail($id);

        // Authorization check
        if ($request->user()->id !== $project->client_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'expected_completion_date' => 'sometimes|nullable|date|after:today',
            'status' => 'sometimes|in:pending,awaiting_quotes,cancelled',
        ]);

        $project->update($validated);

        return response()->json($project);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $project = Project::findOrFail($id);

        // Authorization check
        if (request()->user()->id !== $project->client_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Can only cancel if no payment made
        if (in_array($project->status, ['payment_pending', 'in_progress', 'completed'])) {
            return response()->json(['error' => 'Cannot cancel project at this stage'], 400);
        }

        $project->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => request('reason'),
        ]);

        return response()->json(['message' => 'Project cancelled successfully']);
    }

    /**
     * Search projects by location (for artisans)
     */
    public function search(Request $request)
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius' => 'nullable|numeric|min:1|max:50', // km
            'trade_id' => 'nullable|exists:trades,id',
        ]);

        $lat = $validated['latitude'];
        $lng = $validated['longitude'];
        $radius = ($validated['radius'] ?? 10) * 1000; // Convert to meters

        $query = Project::select('*')
            ->selectRaw('ST_Distance_Sphere(location, POINT(?, ?)) as distance', [$lng, $lat])
            ->whereRaw('ST_Distance_Sphere(location, POINT(?, ?)) <= ?', [$lng, $lat, $radius])
            ->where('status', 'awaiting_quotes')
            ->with(['client', 'trade']);

        if (isset($validated['trade_id'])) {
            $query->where('trade_id', $validated['trade_id']);
        }

        $projects = $query->orderBy('distance', 'asc')->get();

        return response()->json($projects);
    }
}
