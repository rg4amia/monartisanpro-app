<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use MatanYadaev\EloquentSpatial\Objects\Point;

class ProjectController extends Controller
{
 /**
  * Create a new project (quote request)
  */
 public function store(Request $request)
 {
  $validator = Validator::make($request->all(), [
   'artisan_id' => 'required|exists:users,id',
   'trade_id' => 'required|exists:trades,id',
   'title' => 'required|string|max:255',
   'description' => 'required|string|max:2000',
   'latitude' => 'required|numeric|between:-90,90',
   'longitude' => 'required|numeric|between:-180,180',
   'address' => 'required|string|max:500',
   'budget_min' => 'nullable|numeric|min:0',
   'budget_max' => 'nullable|numeric|min:0',
   'expected_completion_date' => 'nullable|date|after:today',
  ]);

  if ($validator->fails()) {
   return response()->json([
    'success' => false,
    'message' => 'Validation échouée',
    'errors' => $validator->errors(),
   ], 422);
  }

  // Verify artisan exists and is active
  $artisan = User::where('id', $request->artisan_id)
   ->where('role', 'artisan')
   ->where('is_suspended', false)
   ->where('is_banned', false)
   ->first();

  if (!$artisan) {
   return response()->json([
    'success' => false,
    'message' => 'Artisan non disponible',
   ], 404);
  }

  try {
   $project = Project::create([
    'client_id' => Auth::id(),
    'artisan_id' => $request->artisan_id,
    'trade_id' => $request->trade_id,
    'title' => $request->title,
    'description' => $request->description,
    'location' => new Point($request->latitude, $request->longitude),
    'address' => $request->address,
    'budget_min' => $request->budget_min,
    'budget_max' => $request->budget_max,
    'expected_completion_date' => $request->expected_completion_date,
    'status' => 'pending',
    'quote_count' => 0,
   ]);

   // Load relationships
   $project->load(['client', 'artisan.artisanProfile.trade', 'trade']);

   // TODO: Send notification to artisan (FCM + SMS)
   // $this->notifyArtisan($artisan, $project);

   return response()->json([
    'success' => true,
    'message' => 'Demande de devis envoyée avec succès',
    'data' => $this->formatProject($project),
   ], 201);
  } catch (\Exception $e) {
   return response()->json([
    'success' => false,
    'message' => 'Erreur lors de la création du projet',
    'error' => $e->getMessage(),
   ], 500);
  }
 }

 /**
  * Get user's projects
  */
 public function index(Request $request)
 {
  $user = Auth::user();
  $status = $request->query('status');
  $role = $request->query('role', $user->role);

  $query = Project::with(['client', 'artisan.artisanProfile.trade', 'trade']);

  if ($role === 'client') {
   $query->where('client_id', $user->id);
  } elseif ($role === 'artisan') {
   $query->where('artisan_id', $user->id);
  }

  if ($status) {
   $query->where('status', $status);
  }

  $projects = $query->orderBy('created_at', 'desc')->get();

  return response()->json([
   'success' => true,
   'data' => $projects->map(fn($project) => $this->formatProject($project)),
  ]);
 }

 /**
  * Get single project details
  */
 public function show($id)
 {
  $project = Project::with([
   'client',
   'artisan.artisanProfile.trade',
   'trade',
   'quotes.items',
   'milestones',
   'escrowWallet',
  ])->find($id);

  if (!$project) {
   return response()->json([
    'success' => false,
    'message' => 'Projet non trouvé',
   ], 404);
  }

  // Check authorization
  $user = Auth::user();
  if ($project->client_id !== $user->id && $project->artisan_id !== $user->id) {
   return response()->json([
    'success' => false,
    'message' => 'Accès non autorisé',
   ], 403);
  }

  return response()->json([
   'success' => true,
   'data' => $this->formatProjectDetails($project),
  ]);
 }

 /**
  * Cancel a project
  */
 public function cancel(Request $request, $id)
 {
  $validator = Validator::make($request->all(), [
   'reason' => 'required|string|max:500',
  ]);

  if ($validator->fails()) {
   return response()->json([
    'success' => false,
    'message' => 'Validation échouée',
    'errors' => $validator->errors(),
   ], 422);
  }

  $project = Project::find($id);

  if (!$project) {
   return response()->json([
    'success' => false,
    'message' => 'Projet non trouvé',
   ], 404);
  }

  // Only client can cancel
  if ($project->client_id !== Auth::id()) {
   return response()->json([
    'success' => false,
    'message' => 'Seul le client peut annuler le projet',
   ], 403);
  }

  // Can only cancel pending or quoted projects
  if (!in_array($project->status, ['pending', 'quoted'])) {
   return response()->json([
    'success' => false,
    'message' => 'Ce projet ne peut plus être annulé',
   ], 400);
  }

  $project->update([
   'status' => 'cancelled',
   'cancelled_at' => now(),
   'cancellation_reason' => $request->reason,
  ]);

  return response()->json([
   'success' => true,
   'message' => 'Projet annulé avec succès',
  ]);
 }

 /**
  * Format project for response
  */
 private function formatProject($project)
 {
  return [
   'id' => $project->id,
   'title' => $project->title,
   'description' => $project->description,
   'status' => $project->status,
   'address' => $project->address,
   'latitude' => $project->location?->latitude,
   'longitude' => $project->location?->longitude,
   'budget_min' => $project->budget_min,
   'budget_max' => $project->budget_max,
   'final_amount' => $project->final_amount,
   'expected_completion_date' => $project->expected_completion_date?->format('Y-m-d'),
   'quote_count' => $project->quote_count,
   'created_at' => $project->created_at->toIso8601String(),
   'client' => [
    'id' => $project->client->id,
    'name' => $project->client->name,
    'phone' => $project->client->phone,
    'avatar' => $project->client->avatar,
   ],
   'artisan' => [
    'id' => $project->artisan->id,
    'name' => $project->artisan->name,
    'phone' => $project->artisan->phone,
    'avatar' => $project->artisan->avatar,
    'trade_name' => $project->artisan->artisanProfile?->trade?->name,
   ],
   'trade' => [
    'id' => $project->trade->id,
    'name' => $project->trade->name,
   ],
  ];
 }

 /**
  * Format project details with quotes and milestones
  */
 private function formatProjectDetails($project)
 {
  $data = $this->formatProject($project);

  $data['quotes'] = $project->quotes->map(function ($quote) {
   return [
    'id' => $quote->id,
    'total_amount' => $quote->total_amount,
    'material_amount' => $quote->material_amount,
    'labor_amount' => $quote->labor_amount,
    'status' => $quote->status,
    'valid_until' => $quote->valid_until?->toIso8601String(),
    'notes' => $quote->notes,
    'items' => $quote->items->map(fn($item) => [
     'type' => $item->type,
     'description' => $item->description,
     'quantity' => $item->quantity,
     'unit' => $item->unit,
     'unit_price' => $item->unit_price,
     'total' => $item->total,
    ]),
   ];
  });

  $data['milestones'] = $project->milestones->map(fn($milestone) => [
   'id' => $milestone->id,
   'name' => $milestone->name,
   'description' => $milestone->description,
   'percentage' => $milestone->percentage,
   'amount' => $milestone->amount,
   'status' => $milestone->status,
   'photo_url' => $milestone->photo_url,
  ]);

  if ($project->escrowWallet) {
   $data['escrow'] = [
    'total_amount' => $project->escrowWallet->total_amount,
    'material_amount' => $project->escrowWallet->material_amount,
    'labor_amount' => $project->escrowWallet->labor_amount,
    'status' => $project->escrowWallet->status,
   ];
  }

  return $data;
 }
}
