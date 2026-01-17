<?php

namespace App\Http\Controllers\Api\V1\Dispute;

use App\Domain\Dispute\Models\Litige\Litige;
use App\Domain\Dispute\Models\ValueObjects\DisputeType;
use App\Domain\Dispute\Models\ValueObjects\LitigeId;
use App\Domain\Dispute\Repositories\LitigeRepository;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispute\CreateDisputeRequest;
use App\Http\Requests\Dispute\StartMediationRequest;
use App\Http\Requests\Dispute\SendMediationMessageRequest;
use App\Http\Requests\Dispute\RenderArbitrationRequest;
use App\Http\Resources\Dispute\DisputeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Controller for dispute resolution API endpoints
 *
 * Requirements: 9.1, 9.5, 9.6
 */
class DisputeController extends Controller
{
 public function __construct(
  private LitigeRepository $litigeRepository
 ) {}

 /**
  * Report a new dispute
  *
  * POST /api/v1/disputes
  * Requirement 9.1: Create Litige record with description and evidence
  */
 public function store(CreateDisputeRequest $request): JsonResponse
 {
  $validated = $request->validated();

  $reporterId = UserId::fromString(Auth::id());
  $missionId = MissionId::fromString($validated['mission_id']);
  $defendantId = UserId::fromString($validated['defendant_id']);
  $type = DisputeType::fromString($validated['type']);

  $litige = Litige::create(
   $missionId,
   $reporterId,
   $defendantId,
   $type,
   $validated['description'],
   $validated['evidence'] ?? []
  );

  $this->litigeRepository->save($litige);

  return response()->json([
   'message' => 'Dispute reported successfully',
   'data' => new DisputeResource($litige)
  ], 201);
 }

 /**
  * Get dispute details
  *
  * GET /api/v1/disputes/{id}
  * Requirement 9.1: View dispute information
  */
 public function show(string $id): JsonResponse
 {
  $litigeId = LitigeId::fromString($id);
  $litige = $this->litigeRepository->findById($litigeId);

  if (!$litige) {
   return response()->json([
    'message' => 'Dispute not found'
   ], 404);
  }

  $userId = UserId::fromString(Auth::id());

  // Check if user is involved in the dispute or is an admin
  if (!$litige->involvesUser($userId) && !$this->isAdmin()) {
   return response()->json([
    'message' => 'Access denied'
   ], 403);
  }

  return response()->json([
   'data' => new DisputeResource($litige)
  ]);
 }

 /**
  * Start mediation for a dispute
  *
  * POST /api/v1/disputes/{id}/mediation/start
  * Requirement 9.3: Assign mediator based on chantier value
  */
 public function startMediation(string $id, StartMediationRequest $request): JsonResponse
 {
  $litigeId = LitigeId::fromString($id);
  $litige = $this->litigeRepository->findById($litigeId);

  if (!$litige) {
   return response()->json([
    'message' => 'Dispute not found'
   ], 404);
  }

  // Only admins can start mediation
  if (!$this->isAdmin()) {
   return response()->json([
    'message' => 'Access denied'
   ], 403);
  }

  $validated = $request->validated();
  $mediatorId = UserId::fromString($validated['mediator_id']);

  try {
   $litige->startMediation($mediatorId);
   $this->litigeRepository->save($litige);

   return response()->json([
    'message' => 'Mediation started successfully',
    'data' => new DisputeResource($litige)
   ]);
  } catch (\InvalidArgumentException $e) {
   return response()->json([
    'message' => $e->getMessage()
   ], 400);
  }
 }

 /**
  * Send message in mediation
  *
  * POST /api/v1/disputes/{id}/mediation/message
  * Requirement 9.5: Provide communication channel duringmediation
  */
 public function sendMediationMessage(string $id, SendMediationMessageRequest $request): JsonResponse
 {
  $litigeId = LitigeId::fromString($id);
  $litige = $this->litigeRepository->findById($litigeId);

  if (!$litige) {
   return response()->json([
    'message' => 'Dispute not found'
   ], 404);
  }

  $userId = UserId::fromString(Auth::id());

  // Check if user is involved in the dispute or is the mediator
  if (
   !$litige->involvesUser($userId) &&
   !($litige->getMediation() && $litige->getMediation()->getMediatorId()->equals($userId))
  ) {
   return response()->json([
    'message' => 'Access denied'
   ], 403);
  }

  $mediation = $litige->getMediation();
  if (!$mediation || !$mediation->isActive()) {
   return response()->json([
    'message' => 'No active mediation for this dispute'
   ], 400);
  }

  $validated = $request->validated();

  try {
   $mediation->addCommunication($validated['message'], $userId);
   $this->litigeRepository->save($litige);

   return response()->json([
    'message' => 'Message sent successfully',
    'data' => new DisputeResource($litige)
   ]);
  } catch (\InvalidArgumentException $e) {
   return response()->json([
    'message' => $e->getMessage()
   ], 400);
  }
 }

 /**
  * Render arbitration decision
  *
  * POST /api/v1/disputes/{id}/arbitration/render
  * Requirement 9.6: Execute arbitration decision
  */
 public function renderArbitration(string $id, RenderArbitrationRequest $request): JsonResponse
 {
  $litigeId = LitigeId::fromString($id);
  $litige = $this->litigeRepository->findById($litigeId);

  if (!$litige) {
   return response()->json([
    'message' => 'Dispute not found'
   ], 404);
  }

  // Only admins can render arbitration decisions
  if (!$this->isAdmin()) {
   return response()->json([
    'message' => 'Access denied'
   ], 403);
  }

  $validated = $request->validated();

  try {
   $arbitratorId = UserId::fromString(Auth::id());
   $decision = new \App\Domain\Dispute\Models\ValueObjects\ArbitrationDecision(
    \App\Domain\Dispute\Models\ValueObjects\DecisionType::fromString($validated['decision_type']),
    isset($validated['amount_centimes'])
     ? \App\Domain\Shared\ValueObjects\MoneyAmount::fromCentimes($validated['amount_centimes'])
     : null
   );

   $arbitration = \App\Domain\Dispute\Models\Arbitrage\Arbitration::renderDecision(
    $arbitratorId,
    $decision,
    $validated['justification']
   );

   $litige->renderArbitrationDecision($arbitration);
   $this->litigeRepository->save($litige);

   return response()->json([
    'message' => 'Arbitration decision rendered successfully',
    'data' => new DisputeResource($litige)
   ]);
  } catch (\InvalidArgumentException $e) {
   return response()->json([
    'message' => $e->getMessage()
   ], 400);
  }
 }

 /**
  * Get disputes for the authenticated user
  */
 public function index(Request $request): JsonResponse
 {
  $userId = Auth::id();
  $disputes = $this->litigeRepository->findByUser($userId);

  return response()->json([
   'data' => DisputeResource::collection($disputes)
  ]);
 }

 /**
  * Get all disputes (admin only)
  */
 public function adminIndex(Request $request): JsonResponse
 {
  if (!$this->isAdmin()) {
   return response()->json([
    'message' => 'Access denied'
   ], 403);
  }

  $status = $request->query('status');

  if ($status) {
   $disputes = $this->litigeRepository->findByStatus($status);
  } else {
   $disputes = $this->litigeRepository->findOpenDisputes();
  }

  return response()->json([
   'data' => DisputeResource::collection($disputes)
  ]);
 }

 private function isAdmin(): bool
 {
  // This should check if the authenticated user is an admin
  // Implementation depends on your user role system
  return Auth::user()->user_type === 'ADMIN';
 }
}
