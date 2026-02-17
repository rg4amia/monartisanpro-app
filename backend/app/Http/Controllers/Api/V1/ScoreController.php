<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ArtisanScore;
use App\Models\Project;
use App\Models\Review;
use App\Models\ScoreHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScoreController extends Controller
{
    // N'Zassa Score Weights
    const WEIGHT_RELIABILITY = 0.40;      // 40%
    const WEIGHT_INTEGRITY = 0.30;        // 30%
    const WEIGHT_QUALITY = 0.15;          // 15%
    const WEIGHT_RESPONSIVENESS = 0.10;   // 10%
    const WEIGHT_PROFESSIONALISM = 0.05;  // 5%

    /**
     * Get artisan's N'Zassa score
     */
    public function show(string $artisanId)
    {
        $artisan = User::where('id', $artisanId)
            ->where('role', 'artisan')
            ->firstOrFail();

        $score = ArtisanScore::with('histories')
            ->where('artisan_id', $artisanId)
            ->first();

        if (!$score) {
            // Calculate initial score if not exists
            $score = $this->calculateScore($artisanId);
        }

        return response()->json($score);
    }

    /**
     * Calculate N'Zassa score for an artisan
     */
    public function calculate(string $artisanId)
    {
        $score = $this->calculateScore($artisanId);
        return response()->json($score);
    }

    /**
     * Get score history for an artisan
     */
    public function history(string $artisanId)
    {
        $histories = ScoreHistory::with(['triggeredBy', 'project'])
            ->where('artisan_id', $artisanId)
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json($histories);
    }

    /**
     * N'Zassa Scoring Algorithm
     */
    protected function calculateScore(int $artisanId, string $eventType = null, int $projectId = null)
    {
        DB::beginTransaction();
        try {
            // Get artisan data
            $artisan = User::findOrFail($artisanId);

            // 1. RELIABILITY SCORE (40%) - Project completion rate and timeline adherence
            $reliabilityScore = $this->calculateReliabilityScore($artisanId);

            // 2. INTEGRITY SCORE (30%) - Token usage transparency, dispute rate
            $integrityScore = $this->calculateIntegrityScore($artisanId);

            // 3. QUALITY SCORE (15%) - Client ratings
            $qualityScore = $this->calculateQualityScore($artisanId);

            // 4. RESPONSIVENESS SCORE (10%) - Response time, milestone completion speed
            $responsivenessScore = $this->calculateResponsivenessScore($artisanId);

            // 5. PROFESSIONALISM SCORE (5%) - Communication rating
            $professionalismScore = $this->calculateProfessionalismScore($artisanId);

            // Calculate weighted total
            $totalScore = (
                ($reliabilityScore * self::WEIGHT_RELIABILITY) +
                ($integrityScore * self::WEIGHT_INTEGRITY) +
                ($qualityScore * self::WEIGHT_QUALITY) +
                ($responsivenessScore * self::WEIGHT_RESPONSIVENESS) +
                ($professionalismScore * self::WEIGHT_PROFESSIONALISM)
            );

            // Get projects completed and reviews
            $projectsCompleted = Project::where('artisan_id', $artisanId)
                ->where('status', 'completed')
                ->count();

            $reviews = Review::where('artisan_id', $artisanId)->get();
            $averageRating = $reviews->avg('rating') ?? 0;
            $totalReviews = $reviews->count();

            // Determine badge level
            $badgeLevel = $this->determineBadgeLevel($totalScore, $projectsCompleted);

            // Get or create score record
            $score = ArtisanScore::updateOrCreate(
                ['artisan_id' => $artisanId],
                [
                    'total_score' => round($totalScore, 2),
                    'reliability_score' => round($reliabilityScore, 2),
                    'integrity_score' => round($integrityScore, 2),
                    'quality_score' => round($qualityScore, 2),
                    'responsiveness_score' => round($responsivenessScore, 2),
                    'professionalism_score' => round($professionalismScore, 2),
                    'projects_completed' => $projectsCompleted,
                    'average_rating' => round($averageRating, 2),
                    'total_reviews' => $totalReviews,
                    'badge_level' => $badgeLevel,
                    'last_calculated_at' => now(),
                ]
            );

            // Create history entry
            $previousScore = ScoreHistory::where('artisan_id', $artisanId)
                ->latest()
                ->first();

            $scoreChange = $previousScore
                ? round($totalScore - $previousScore->total_score, 2)
                : 0;

            ScoreHistory::create([
                'artisan_id' => $artisanId,
                'total_score' => round($totalScore, 2),
                'reliability_score' => round($reliabilityScore, 2),
                'integrity_score' => round($integrityScore, 2),
                'quality_score' => round($qualityScore, 2),
                'responsiveness_score' => round($responsivenessScore, 2),
                'professionalism_score' => round($professionalismScore, 2),
                'event_type' => $eventType ?? 'project_completed',
                'event_description' => $eventType ? "Score recalculated after $eventType" : 'Score calculated',
                'score_change' => $scoreChange,
                'triggered_by' => auth()->id(),
                'project_id' => $projectId,
            ]);

            DB::commit();

            return $score;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Calculate Reliability Score (40%)
     * Based on: completion rate, on-time delivery, cancellation rate
     */
    protected function calculateReliabilityScore(int $artisanId): float
    {
        $totalProjects = Project::where('artisan_id', $artisanId)
            ->whereIn('status', ['completed', 'cancelled'])
            ->count();

        if ($totalProjects === 0) return 80.0; // Default for new artisans

        $completedProjects = Project::where('artisan_id', $artisanId)
            ->where('status', 'completed')
            ->count();

        $completionRate = ($completedProjects / $totalProjects) * 100;

        // Penalty for cancellations
        $cancelledProjects = $totalProjects - $completedProjects;
        $cancellationPenalty = $cancelledProjects * 10; // -10 points per cancellation

        $score = $completionRate - $cancellationPenalty;

        return max(0, min(100, $score));
    }

    /**
     * Calculate Integrity Score (30%)
     * Based on: token usage transparency, dispute rate, fraud flags
     */
    protected function calculateIntegrityScore(int $artisanId): float
    {
        $projects = Project::where('artisan_id', $artisanId)->get();

        if ($projects->isEmpty()) return 90.0; // Default for new artisans

        $totalProjects = $projects->count();
        $disputedProjects = $projects->where('status', 'disputed')->count();

        $disputeRate = $disputedProjects / $totalProjects;
        $score = 100 - ($disputeRate * 100); // Reduce score based on dispute rate

        // Check for fraud patterns (GPS violations)
        $fraudFlags = DB::table('token_redemptions')
            ->join('material_tokens', 'token_redemptions.token_id', '=', 'material_tokens.id')
            ->join('projects', 'material_tokens.project_id', '=', 'projects.id')
            ->where('projects.artisan_id', $artisanId)
            ->where('token_redemptions.distance_meters', '>', 100)
            ->where('token_redemptions.validation_method', 'gps')
            ->count();

        $score -= ($fraudFlags * 5); // -5 points per fraud flag

        return max(0, min(100, $score));
    }

    /**
     * Calculate Quality Score (15%)
     * Based on: client ratings (quality_rating)
     */
    protected function calculateQualityScore(int $artisanId): float
    {
        $avgQualityRating = Review::where('artisan_id', $artisanId)
            ->avg('quality_rating');

        if (!$avgQualityRating) return 75.0; // Default for new artisans

        // Convert 1-5 rating to 0-100 score
        return ($avgQualityRating / 5) * 100;
    }

    /**
     * Calculate Responsiveness Score (10%)
     * Based on: quote response time, milestone completion speed
     */
    protected function calculateResponsivenessScore(int $artisanId): float
    {
        $projects = Project::where('artisan_id', $artisanId)
            ->where('status', 'completed')
            ->get();

        if ($projects->isEmpty()) return 80.0; // Default for new artisans

        // Check milestone completion speed
        $avgMilestoneSpeed = DB::table('milestones')
            ->join('projects', 'milestones.project_id', '=', 'projects.id')
            ->where('projects.artisan_id', $artisanId)
            ->where('milestones.status', 'validated')
            ->whereNotNull('milestones.completed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(DAY, milestones.created_at, milestones.completed_at)) as avg_days')
            ->value('avg_days');

        // Score based on speed (faster = higher score)
        if (!$avgMilestoneSpeed) return 80.0;

        if ($avgMilestoneSpeed <= 3) return 100.0;
        if ($avgMilestoneSpeed <= 7) return 85.0;
        if ($avgMilestoneSpeed <= 14) return 70.0;
        return 50.0;
    }

    /**
     * Calculate Professionalism Score (5%)
     * Based on: communication rating
     */
    protected function calculateProfessionalismScore(int $artisanId): float
    {
        $avgCommunicationRating = Review::where('artisan_id', $artisanId)
            ->avg('communication_rating');

        if (!$avgCommunicationRating) return 80.0; // Default for new artisans

        // Convert 1-5 rating to 0-100 score
        return ($avgCommunicationRating / 5) * 100;
    }

    /**
     * Determine badge level based on score and projects completed
     */
    protected function determineBadgeLevel(float $score, int $projectsCompleted): string
    {
        if ($score >= 80 && $projectsCompleted >= 20) return 'gold';
        if ($score >= 65 && $projectsCompleted >= 10) return 'silver';
        if ($score >= 50 && $projectsCompleted >= 5) return 'bronze';
        return 'none';
    }
}
