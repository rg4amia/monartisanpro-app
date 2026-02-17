<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\ScoreController;
use App\Models\Project;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    /**
     * Get reviews for an artisan
     */
    public function index(Request $request)
    {
        $artisanId = $request->query('artisan_id');

        if (!$artisanId) {
            return response()->json(['error' => 'artisan_id required'], 400);
        }

        $reviews = Review::with(['client', 'project'])
            ->where('artisan_id', $artisanId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Calculate stats
        $stats = [
            'total_reviews' => Review::where('artisan_id', $artisanId)->count(),
            'average_rating' => round(Review::where('artisan_id', $artisanId)->avg('rating'), 2),
            'average_quality' => round(Review::where('artisan_id', $artisanId)->avg('quality_rating'), 2),
            'average_communication' => round(Review::where('artisan_id', $artisanId)->avg('communication_rating'), 2),
            'average_timeliness' => round(Review::where('artisan_id', $artisanId)->avg('timeliness_rating'), 2),
            'average_professionalism' => round(Review::where('artisan_id', $artisanId)->avg('professionalism_rating'), 2),
            'would_recommend_percentage' => round(
                Review::where('artisan_id', $artisanId)->where('would_recommend', true)->count() /
                max(Review::where('artisan_id', $artisanId)->count(), 1) * 100,
                1
            ),
        ];

        return response()->json([
            'reviews' => $reviews,
            'stats' => $stats,
        ]);
    }

    /**
     * Create a review (client only, after project completion)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
            'quality_rating' => 'required|integer|min:1|max:5',
            'communication_rating' => 'required|integer|min:1|max:5',
            'timeliness_rating' => 'required|integer|min:1|max:5',
            'professionalism_rating' => 'required|integer|min:1|max:5',
            'would_recommend' => 'required|boolean',
            'photos' => 'nullable|array|max:5',
            'photos.*' => 'image|max:5120', // 5MB per image
        ]);

        $project = Project::with('artisan')->findOrFail($validated['project_id']);

        // Verify user is client for this project
        if ($request->user()->id !== $project->client_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Verify project is completed
        if ($project->status !== 'completed') {
            return response()->json(['error' => 'Can only review completed projects'], 400);
        }

        // Check if review already exists
        if (Review::where('project_id', $project->id)->exists()) {
            return response()->json(['error' => 'Review already submitted for this project'], 400);
        }

        DB::beginTransaction();
        try {
            // Handle photo uploads
            $photoUrls = [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $photoUrls[] = $photo->store('reviews', 'public');
                }
            }

            // Create review
            $review = Review::create([
                'project_id' => $project->id,
                'artisan_id' => $project->artisan_id,
                'client_id' => $request->user()->id,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'quality_rating' => $validated['quality_rating'],
                'communication_rating' => $validated['communication_rating'],
                'timeliness_rating' => $validated['timeliness_rating'],
                'professionalism_rating' => $validated['professionalism_rating'],
                'would_recommend' => $validated['would_recommend'],
                'photos' => $photoUrls,
            ]);

            // Recalculate artisan's N'Zassa score
            $scoreController = new ScoreController();
            $scoreController->calculate($project->artisan_id);

            DB::commit();

            return response()->json($review->load(['client', 'project']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create review'], 500);
        }
    }

    /**
     * Get a specific review
     */
    public function show(string $id)
    {
        $review = Review::with(['client', 'artisan', 'project'])->findOrFail($id);
        return response()->json($review);
    }

    /**
     * Artisan responds to a review
     */
    public function respond(Request $request, string $id)
    {
        $validated = $request->validate([
            'response' => 'required|string|max:500',
        ]);

        $review = Review::findOrFail($id);

        // Verify user is the artisan being reviewed
        if ($request->user()->id !== $review->artisan_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Check if already responded
        if ($review->artisan_response) {
            return response()->json(['error' => 'Already responded to this review'], 400);
        }

        $review->update([
            'artisan_response' => $validated['response'],
            'responded_at' => now(),
        ]);

        return response()->json($review);
    }

    /**
     * Upload review photos
     */
    public function uploadPhotos(Request $request)
    {
        $validated = $request->validate([
            'photos' => 'required|array|max:5',
            'photos.*' => 'image|max:5120', // 5MB per image
        ]);

        $photoUrls = [];
        foreach ($request->file('photos') as $photo) {
            $photoUrls[] = $photo->store('reviews', 'public');
        }

        return response()->json(['photo_urls' => $photoUrls]);
    }
}
