<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuoteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Quote::with(['project', 'artisan', 'items']);

        if ($user->role === 'artisan') {
            $query->where('artisan_id', $user->id);
        } elseif ($user->role === 'client') {
            $query->whereHas('project', function($q) use ($user) {
                $q->where('client_id', $user->id);
            });
        }

        $quotes = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($quotes);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'material_percentage' => 'required|numeric|min:0|max:100',
            'labor_percentage' => 'required|numeric|min:0|max:100',
            'valid_days' => 'required|integer|min:1|max:30',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.type' => 'required|in:material,labor',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.unit' => 'nullable|string',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        // Validate percentages sum to 100
        if (($validated['material_percentage'] + $validated['labor_percentage']) != 100) {
            return response()->json([
                'error' => 'Material and labor percentages must sum to 100'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Calculate totals
            $materialAmount = 0;
            $laborAmount = 0;

            foreach ($validated['items'] as $item) {
                $total = $item['quantity'] * $item['unit_price'];
                if ($item['type'] === 'material') {
                    $materialAmount += $total;
                } else {
                    $laborAmount += $total;
                }
            }

            $totalAmount = $materialAmount + $laborAmount;

            // Create quote
            $quote = Quote::create([
                'project_id' => $validated['project_id'],
                'artisan_id' => $request->user()->id,
                'total_amount' => $totalAmount,
                'material_amount' => $materialAmount,
                'labor_amount' => $laborAmount,
                'material_percentage' => $validated['material_percentage'],
                'labor_percentage' => $validated['labor_percentage'],
                'valid_days' => $validated['valid_days'],
                'valid_until' => now()->addDays($validated['valid_days']),
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
            ]);

            // Create quote items
            foreach ($validated['items'] as $item) {
                QuoteItem::create([
                    'quote_id' => $quote->id,
                    'type' => $item['type'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'] ?? null,
                    'unit_price' => $item['unit_price'],
                    'total' => $item['quantity'] * $item['unit_price'],
                ]);
            }

            // Update project quote count
            $project = Project::find($validated['project_id']);
            $project->increment('quote_count');

            DB::commit();

            return response()->json($quote->load('items'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create quote'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $quote = Quote::with(['project', 'artisan', 'items'])->findOrFail($id);
        return response()->json($quote);
    }

    /**
     * Send quote to client
     */
    public function send(string $id)
    {
        $quote = Quote::findOrFail($id);

        if ($quote->status !== 'draft') {
            return response()->json(['error' => 'Quote already sent'], 400);
        }

        $quote->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        // Update project status
        $quote->project->update(['status' => 'quote_received']);

        return response()->json($quote);
    }

    /**
     * Accept quote (client only)
     */
    public function accept(string $id)
    {
        $quote = Quote::with('project')->findOrFail($id);

        if ($quote->project->client_id !== request()->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($quote->status !== 'sent') {
            return response()->json(['error' => 'Quote cannot be accepted'], 400);
        }

        DB::beginTransaction();
        try {
            $quote->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            $quote->project->update([
                'status' => 'quote_accepted',
                'artisan_id' => $quote->artisan_id,
                'final_amount' => $quote->total_amount,
            ]);

            DB::commit();

            return response()->json($quote);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to accept quote'], 500);
        }
    }

    /**
     * Reject quote (client only)
     */
    public function reject(Request $request, string $id)
    {
        $quote = Quote::with('project')->findOrFail($id);

        if ($quote->project->client_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $quote->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejection_reason' => $validated['reason'],
        ]);

        return response()->json($quote);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $quote = Quote::findOrFail($id);

        if ($quote->artisan_id !== request()->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($quote->status !== 'draft') {
            return response()->json(['error' => 'Cannot delete sent quote'], 400);
        }

        $quote->delete();

        return response()->json(['message' => 'Quote deleted successfully']);
    }
}
