<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\DisputeMessage;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DisputeController extends Controller
{
    /**
     * Get all disputes for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $status = $request->query('status');

        $query = Dispute::where(function ($q) use ($user) {
            $q->where('initiator_id', $user->id)
              ->orWhere('respondent_id', $user->id);
        })
        ->with([
            'project:id,title',
            'initiator:id,name,avatar',
            'respondent:id,name,avatar',
        ])
        ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $disputes = $query->get()->map(function ($dispute) use ($user) {
            return [
                'id' => $dispute->id,
                'project_id' => $dispute->project_id,
                'project' => $dispute->project ? [
                    'id' => $dispute->project->id,
                    'title' => $dispute->project->title,
                ] : null,
                'dispute_type' => $dispute->dispute_type,
                'reason' => $dispute->reason,
                'description' => $dispute->description,
                'status' => $dispute->status,
                'priority' => $dispute->priority,
                'evidence' => $dispute->evidence,
                'resolution' => $dispute->resolution,
                'resolution_type' => $dispute->resolution_type,
                'resolved_at' => $dispute->resolved_at,
                'created_at' => $dispute->created_at,
                'initiator' => [
                    'id' => $dispute->initiator->id,
                    'name' => $dispute->initiator->name,
                    'avatar' => $dispute->initiator->avatar,
                ],
                'respondent' => $dispute->respondent ? [
                    'id' => $dispute->respondent->id,
                    'name' => $dispute->respondent->name,
                    'avatar' => $dispute->respondent->avatar,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $disputes,
        ]);
    }

    /**
     * Get a specific dispute with messages
     */
    public function show(int $id): JsonResponse
    {
        $user = auth()->user();

        $dispute = Dispute::with([
            'project:id,title',
            'initiator:id,name,avatar',
            'respondent:id,name,avatar',
            'messages.user:id,name,avatar',
        ])->findOrFail($id);

        // Verify user is part of the dispute
        if ($dispute->initiator_id !== $user->id && $dispute->respondent_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas accès à ce litige',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $dispute->id,
                'project_id' => $dispute->project_id,
                'project' => $dispute->project ? [
                    'id' => $dispute->project->id,
                    'title' => $dispute->project->title,
                ] : null,
                'dispute_type' => $dispute->dispute_type,
                'reason' => $dispute->reason,
                'description' => $dispute->description,
                'status' => $dispute->status,
                'priority' => $dispute->priority,
                'evidence' => $dispute->evidence,
                'resolution' => $dispute->resolution,
                'resolution_type' => $dispute->resolution_type,
                'resolved_at' => $dispute->resolved_at,
                'created_at' => $dispute->created_at,
                'messages' => $dispute->messages->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'message' => $message->message,
                        'attachments' => $message->attachments,
                        'is_admin_message' => $message->is_admin_message,
                        'created_at' => $message->created_at,
                        'user' => [
                            'id' => $message->user->id,
                            'name' => $message->user->name,
                            'avatar' => $message->user->avatar,
                        ],
                    ];
                }),
            ],
        ]);
    }

    /**
     * Create a new dispute
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'dispute_type' => 'required|in:quality,payment,delay,fraud,other',
            'reason' => 'required|string|max:255',
            'description' => 'required|string|max:2000',
            'evidence' => 'nullable|array|max:5',
            'evidence.*' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:5120', // 5MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verify user is part of the project
        $project = Project::findOrFail($request->project_id);
        if ($project->client_id !== $user->id && $project->artisan_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à créer un litige pour ce projet',
            ], 403);
        }

        // Determine respondent (the other party in the project)
        $respondentId = $project->client_id === $user->id
            ? $project->artisan_id
            : $project->client_id;

        // Handle evidence uploads
        $evidenceUrls = [];
        if ($request->hasFile('evidence')) {
            foreach ($request->file('evidence') as $file) {
                $path = $file->store('disputes/' . $request->project_id, 'public');
                $evidenceUrls[] = Storage::url($path);
            }
        }

        // Auto-determine priority based on dispute type
        $priority = match ($request->dispute_type) {
            'fraud' => 'urgent',
            'payment' => 'high',
            'quality', 'delay' => 'medium',
            default => 'low',
        };

        $dispute = Dispute::create([
            'project_id' => $request->project_id,
            'initiator_id' => $user->id,
            'respondent_id' => $respondentId,
            'dispute_type' => $request->dispute_type,
            'reason' => $request->reason,
            'description' => $request->description,
            'status' => 'open',
            'priority' => $priority,
            'evidence' => !empty($evidenceUrls) ? $evidenceUrls : null,
        ]);

        // Update project status to disputed
        $project->update(['status' => 'disputed']);

        // TODO: Send notification to respondent and admin

        $dispute->load(['project:id,title', 'initiator:id,name,avatar', 'respondent:id,name,avatar']);

        return response()->json([
            'success' => true,
            'message' => 'Litige créé avec succès',
            'data' => [
                'id' => $dispute->id,
                'project_id' => $dispute->project_id,
                'project' => [
                    'id' => $dispute->project->id,
                    'title' => $dispute->project->title,
                ],
                'dispute_type' => $dispute->dispute_type,
                'reason' => $dispute->reason,
                'description' => $dispute->description,
                'status' => $dispute->status,
                'priority' => $dispute->priority,
                'evidence' => $dispute->evidence,
                'created_at' => $dispute->created_at,
            ],
        ], 201);
    }

    /**
     * Send a message in a dispute
     */
    public function sendMessage(Request $request, int $disputeId): JsonResponse
    {
        $user = auth()->user();

        $dispute = Dispute::findOrFail($disputeId);

        // Verify user is part of the dispute
        if ($dispute->initiator_id !== $user->id && $dispute->respondent_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas accès à ce litige',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:5000',
            'attachments' => 'nullable|array|max:3',
            'attachments.*' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Handle attachments
        $attachmentUrls = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('disputes/' . $disputeId . '/messages', 'public');
                $attachmentUrls[] = Storage::url($path);
            }
        }

        $message = DisputeMessage::create([
            'dispute_id' => $disputeId,
            'user_id' => $user->id,
            'message' => $request->message,
            'attachments' => !empty($attachmentUrls) ? $attachmentUrls : null,
            'is_admin_message' => false,
        ]);

        $message->load('user:id,name,avatar');

        // TODO: Send notification to the other party

        return response()->json([
            'success' => true,
            'message' => 'Message envoyé avec succès',
            'data' => [
                'id' => $message->id,
                'message' => $message->message,
                'attachments' => $message->attachments,
                'is_admin_message' => $message->is_admin_message,
                'created_at' => $message->created_at,
                'user' => [
                    'id' => $message->user->id,
                    'name' => $message->user->name,
                    'avatar' => $message->user->avatar,
                ],
            ],
        ], 201);
    }
}
