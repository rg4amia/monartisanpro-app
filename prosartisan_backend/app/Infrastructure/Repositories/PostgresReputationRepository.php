<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Reputation\Models\ReputationProfile\ReputationProfile;
use App\Domain\Reputation\Models\ValueObjects\ProfileId;
use App\Domain\Reputation\Models\ValueObjects\NZassaScore;
use App\Domain\Reputation\Models\ValueObjects\ReputationMetrics;
use App\Domain\Reputation\Models\ValueObjects\ScoreSnapshot;
use App\Domain\Reputation\Repositories\ReputationRepository;
use Illuminate\Support\Facades\DB;
use DateTime;

/**
 * PostgreSQL implementation of ReputationRepository
 */
class PostgresReputationRepository implements ReputationRepository
{
 public function save(ReputationProfile $profile): void
 {
  DB::transaction(function () use ($profile) {
   // Save or update reputation profile
   DB::table('reputation_profiles')->updateOrInsert(
    ['id' => $profile->getId()->getValue()],
    [
     'artisan_id' => $profile->getArtisanId()->getValue(),
     'current_score' => $profile->getCurrentScore()->getValue(),
     'reliability_score' => $profile->getMetrics()->getReliabilityScore(),
     'integrity_score' => $profile->getMetrics()->getIntegrityScore(),
     'quality_score' => $profile->getMetrics()->getQualityScore(),
     'reactivity_score' => $profile->getMetrics()->getReactivityScore(),
     'completed_projects' => $profile->getMetrics()->getCompletedProjects(),
     'accepted_projects' => $profile->getMetrics()->getAcceptedProjects(),
     'average_rating' => $profile->getMetrics()->getAverageRating(),
     'average_response_time_hours' => $profile->getMetrics()->getAverageResponseTimeHours(),
     'fraud_attempts' => $profile->getMetrics()->getFraudAttempts(),
     'last_calculated_at' => $profile->getLastCalculatedAt()->format('Y-m-d H:i:s'),
     'created_at' => $profile->getCreatedAt()->format('Y-m-d H:i:s'),
     'updated_at' => $profile->getUpdatedAt()->format('Y-m-d H:i:s'),
    ]
   );

   // Save score history
   $this->saveScoreHistory($profile->getId(), $profile->getScoreHistory());
  });
 }

 public function findById(ProfileId $id): ?ReputationProfile
 {
  $data = DB::table('reputation_profiles')
   ->where('id', $id->getValue())
   ->first();

  if (!$data) {
   return null;
  }

  return $this->mapToReputationProfile($data);
 }

 public function findByArtisanId(UserId $artisanId): ?ReputationProfile
 {
  $data = DB::table('reputation_profiles')
   ->where('artisan_id', $artisanId->getValue())
   ->first();

  if (!$data) {
   return null;
  }

  return $this->mapToReputationProfile($data);
 }

 public function findTopArtisans(int $limit): array
 {
  $results = DB::table('reputation_profiles')
   ->orderBy('current_score', 'desc')
   ->limit($limit)
   ->get();

  return $results->map(fn($data) => $this->mapToReputationProfile($data))->toArray();
 }

 public function findEligibleForMicroCredit(): array
 {
  $results = DB::table('reputation_profiles')
   ->where('current_score', '>', 70)
   ->orderBy('current_score', 'desc')
   ->get();

  return $results->map(fn($data) => $this->mapToReputationProfile($data))->toArray();
 }

 private function mapToReputationProfile($data): ReputationProfile
 {
  $metrics = new ReputationMetrics(
   $data->reliability_score,
   $data->integrity_score,
   $data->quality_score,
   $data->reactivity_score,
   $data->completed_projects,
   $data->accepted_projects,
   $data->average_rating,
   $data->average_response_time_hours,
   $data->fraud_attempts
  );

  $scoreHistory = $this->loadScoreHistory(ProfileId::fromString($data->id));

  return new ReputationProfile(
   ProfileId::fromString($data->id),
   UserId::fromString($data->artisan_id),
   NZassaScore::fromInt($data->current_score),
   $metrics,
   $scoreHistory,
   new DateTime($data->last_calculated_at),
   new DateTime($data->created_at),
   new DateTime($data->updated_at)
  );
 }

 private function loadScoreHistory(ProfileId $profileId): array
 {
  $historyData = DB::table('score_history')
   ->where('profile_id', $profileId->getValue())
   ->orderBy('recorded_at', 'asc')
   ->get();

  return $historyData->map(function ($data) {
   return new ScoreSnapshot(
    NZassaScore::fromInt($data->score),
    $data->reason,
    new DateTime($data->recorded_at)
   );
  })->toArray();
 }

 private function saveScoreHistory(ProfileId $profileId, array $scoreHistory): void
 {
  // Delete existing history
  DB::table('score_history')
   ->where('profile_id', $profileId->getValue())
   ->delete();

  // Insert new history
  foreach ($scoreHistory as $snapshot) {
   DB::table('score_history')->insert([
    'id' => \Illuminate\Support\Str::uuid(),
    'profile_id' => $profileId->getValue(),
    'score' => $snapshot->getScore()->getValue(),
    'reason' => $snapshot->getReason(),
    'recorded_at' => $snapshot->getRecordedAt()->format('Y-m-d H:i:s'),
    'created_at' => now(),
    'updated_at' => now(),
   ]);
  }
 }
}
