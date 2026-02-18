<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    /**
     * Get all conversations for the authenticated user
     * Returns list of projects with latest message and unread count
     */
    public function getConversations(): JsonResponse
    {
        $user = auth()->user();

        // Get projects where user is either client or artisan
        $projects = Project::where(function ($query) use ($user) {
            $query->where('client_id', $user->id)
                  ->orWhere('artisan_id', $user->id);
        })
        ->whereNotNull('artisan_id') // Only projects with assigned artisan
        ->with([
            'client:id,name,avatar',
            'artisan:id,name,avatar',
            'trade:id,name',
            'messages' => function ($query) {
                $query->latest()->limit(1);
            }
        ])
        ->get()
        ->map(function ($project) use ($user) {
            $otherUser = $project->client_id === $user->id
                ? $project->artisan
                : $project->client;

            $unreadCount = $project->messages()
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at')
                ->count();

            return [
                'id' => $project->id,
                'title' => $project->title,
                'trade_name' => $project->trade->name ?? null,
                'status' => $project->status,
                'other_user' => $otherUser ? [
                    'id' => $otherUser->id,
                    'name' => $otherUser->name,
                    'avatar' => $otherUser->avatar,
                ] : null,
                'last_message' => $project->messages->first() ? [
                    'message' => $project->messages->first()->message,
                    'created_at' => $project->messages->first()->created_at,
                    'sender_id' => $project->messages->first()->sender_id,
                ] : null,
                'unread_count' => $unreadCount,
                'updated_at' => $project->messages->first()->created_at ?? $project->updated_at,
            ];
        })
        ->sortByDesc('updated_at')
        ->values();

        return response()->json([
            'success' => true,
            'data' => $projects,
        ]);
    }

    /**
     * Get all messages for a specific project
     */
    public function getProjectMessages(int $projectId): JsonResponse
    {
        $user = auth()->user();

        $project = Project::findOrFail($projectId);

        // Verify user is part of the project
        if ($project->client_id !== $user->id && $project->artisan_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas accès à cette conversation',
            ], 403);
        }

        $messages = ProjectMessage::where('project_id', $projectId)
            ->with('sender:id,name,avatar')
            ->orderBy('created_at')
            ->get()
            ->map(function ($message) use ($user) {
                return [
                    'id' => $message->id,
                    'message' => $message->message,
                    'sender' => [
                        'id' => $message->sender->id,
                        'name' => $message->sender->name,
                        'avatar' => $message->sender->avatar,
                    ],
                    'is_own_message' => $message->sender_id === $user->id,
                    'attachments' => $message->attachments,
                    'read_at' => $message->read_at,
                    'created_at' => $message->created_at,
                ];
            });

        // Mark all messages from other user as read
        ProjectMessage::where('project_id', $projectId)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'data' => $messages,
        ]);
    }

    /**
     * Send a new message
     */
    public function sendMessage(Request $request, int $projectId): JsonResponse
    {
        $user = auth()->user();

        $project = Project::findOrFail($projectId);

        // Verify user is part of the project
        if ($project->client_id !== $user->id && $project->artisan_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas accès à cette conversation',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:5000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $attachmentUrls = [];

        // Handle file uploads
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('messages/' . $projectId, 'public');
                $attachmentUrls[] = Storage::url($path);
            }
        }

        $message = ProjectMessage::create([
            'project_id' => $projectId,
            'sender_id' => $user->id,
            'message' => $request->message,
            'attachments' => !empty($attachmentUrls) ? $attachmentUrls : null,
        ]);

        $message->load('sender:id,name,avatar');

        // TODO: Send push notification to the other user

        return response()->json([
            'success' => true,
            'message' => 'Message envoyé avec succès',
            'data' => [
                'id' => $message->id,
                'message' => $message->message,
                'sender' => [
                    'id' => $message->sender->id,
                    'name' => $message->sender->name,
                    'avatar' => $message->sender->avatar,
                ],
                'is_own_message' => true,
                'attachments' => $message->attachments,
                'read_at' => $message->read_at,
                'created_at' => $message->created_at,
            ],
        ], 201);
    }

    /**
     * Mark a message as read
     */
    public function markAsRead(int $messageId): JsonResponse
    {
        $user = auth()->user();

        $message = ProjectMessage::findOrFail($messageId);

        // Only the recipient can mark as read
        if ($message->sender_id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas marquer votre propre message comme lu',
            ], 403);
        }

        // Verify user is part of the project
        $project = $message->project;
        if ($project->client_id !== $user->id && $project->artisan_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas accès à ce message',
            ], 403);
        }

        $message->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Message marqué comme lu',
        ]);
    }
}
