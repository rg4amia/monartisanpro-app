<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LaborPayment;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MilestoneController extends Controller
{
    /**
     * Get milestones for a project
     */
    public function index(Request $request)
    {
        $projectId = $request->query('project_id');

        if (!$projectId) {
            return response()->json(['error' => 'project_id required'], 400);
        }

        $milestones = Milestone::with('laborPayment')
            ->where('project_id', $projectId)
            ->orderBy('sequence_order', 'asc')
            ->get();

        return response()->json($milestones);
    }

    /**
     * Create milestones for a project
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'milestones' => 'required|array|min:1',
            'milestones.*.title' => 'required|string|max:255',
            'milestones.*.description' => 'nullable|string',
            'milestones.*.labor_percentage' => 'required|numeric|min:0|max:100',
            'milestones.*.requires_photo' => 'boolean',
            'milestones.*.requires_otp' => 'boolean',
        ]);

        $project = Project::findOrFail($validated['project_id']);

        // Verify user is artisan for this project
        if ($request->user()->id !== $project->artisan_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Validate percentages sum to 100
        $totalPercentage = array_sum(array_column($validated['milestones'], 'labor_percentage'));
        if ($totalPercentage != 100) {
            return response()->json(['error' => 'Labor percentages must sum to 100'], 422);
        }

        DB::beginTransaction();
        try {
            $createdMilestones = [];
            foreach ($validated['milestones'] as $index => $milestoneData) {
                $milestone = Milestone::create([
                    'project_id' => $validated['project_id'],
                    'title' => $milestoneData['title'],
                    'description' => $milestoneData['description'] ?? null,
                    'labor_percentage' => $milestoneData['labor_percentage'],
                    'sequence_order' => $index + 1,
                    'requires_photo' => $milestoneData['requires_photo'] ?? false,
                    'requires_otp' => $milestoneData['requires_otp'] ?? true,
                    'status' => 'pending',
                ]);
                $createdMilestones[] = $milestone;
            }

            DB::commit();
            return response()->json($createdMilestones, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create milestones'], 500);
        }
    }

    /**
     * Complete milestone (artisan uploads photo proof)
     */
    public function complete(Request $request, string $id)
    {
        $validated = $request->validate([
            'photo' => 'required_if:requires_photo,true|image|max:10240', // 10MB
        ]);

        $milestone = Milestone::with('project')->findOrFail($id);

        // Verify user is artisan
        if ($request->user()->id !== $milestone->project->artisan_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($milestone->status !== 'pending' && $milestone->status !== 'in_progress') {
            return response()->json(['error' => 'Milestone cannot be completed'], 400);
        }

        // Handle photo upload
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('milestones', 'public');
        } elseif ($milestone->requires_photo) {
            return response()->json(['error' => 'Photo required for this milestone'], 400);
        }

        $milestone->update([
            'photo_path' => $photoPath,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json($milestone);
    }

    /**
     * Send OTP to client for validation
     */
    public function sendOtp(string $id)
    {
        $milestone = Milestone::with('project.client')->findOrFail($id);

        if ($milestone->status !== 'completed') {
            return response()->json(['error' => 'Milestone must be completed first'], 400);
        }

        // Generate 6-digit OTP
        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $milestone->update([
            'otp_code' => $otpCode,
        ]);

        // TODO: Send OTP via SMS to client phone
        // For now, return OTP (remove in production)
        // $this->sendSms($milestone->project->client->phone, "Your ProsArtisan OTP: $otpCode");

        return response()->json([
            'message' => 'OTP sent successfully',
            'otp_code' => $otpCode, // REMOVE IN PRODUCTION
        ]);
    }

    /**
     * Validate milestone with OTP (client validates)
     */
    public function validate(Request $request, string $id)
    {
        $validated = $request->validate([
            'otp_code' => 'required|string|size:6',
        ]);

        $milestone = Milestone::with(['project.escrowWallet', 'project.artisan'])->findOrFail($id);

        // Verify user is client
        if ($request->user()->id !== $milestone->project->client_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($milestone->status !== 'completed') {
            return response()->json(['error' => 'Milestone not ready for validation'], 400);
        }

        // Verify OTP
        if ($milestone->otp_code !== $validated['otp_code']) {
            return response()->json(['error' => 'Invalid OTP code'], 400);
        }

        DB::beginTransaction();
        try {
            // Update milestone
            $milestone->update([
                'status' => 'validated',
                'validated_at' => now(),
                'otp_verified_at' => now(),
            ]);

            // Schedule labor payment (J+1)
            $laborAmount = ($milestone->project->escrowWallet->labor_wallet * $milestone->labor_percentage) / 100;

            $laborPayment = LaborPayment::create([
                'milestone_id' => $milestone->id,
                'artisan_id' => $milestone->project->artisan_id,
                'escrow_wallet_id' => $milestone->project->escrowWallet->id,
                'amount' => $laborAmount,
                'status' => 'scheduled',
                'scheduled_at' => now()->addDay(), // J+1
            ]);

            // Create transaction record
            $transaction = Transaction::create([
                'project_id' => $milestone->project_id,
                'user_id' => $milestone->project->artisan_id,
                'transaction_ref' => 'LABOR-' . $milestone->id . '-' . time(),
                'type' => 'labor_release',
                'amount' => $laborAmount,
                'status' => 'pending',
                'metadata' => [
                    'milestone_id' => $milestone->id,
                    'labor_payment_id' => $laborPayment->id,
                    'scheduled_payout' => $laborPayment->scheduled_at->toDateTimeString(),
                ],
            ]);

            $laborPayment->update(['transaction_id' => $transaction->id]);

            DB::commit();

            return response()->json([
                'milestone' => $milestone,
                'labor_payment' => $laborPayment,
                'message' => 'Milestone validated. Payment scheduled for ' . $laborPayment->scheduled_at->toDateString(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Validation failed'], 500);
        }
    }

    /**
     * Get milestone details
     */
    public function show(string $id)
    {
        $milestone = Milestone::with(['project', 'laborPayment'])->findOrFail($id);
        return response()->json($milestone);
    }

    /**
     * Update milestone
     */
    public function update(Request $request, string $id)
    {
        $milestone = Milestone::with('project')->findOrFail($id);

        // Verify user is artisan
        if ($request->user()->id !== $milestone->project->artisan_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($milestone->status !== 'pending') {
            return response()->json(['error' => 'Cannot update started milestone'], 400);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'status' => 'sometimes|in:pending,in_progress',
        ]);

        $milestone->update($validated);

        return response()->json($milestone);
    }

    /**
     * Delete milestone (only if pending)
     */
    public function destroy(string $id)
    {
        $milestone = Milestone::with('project')->findOrFail($id);

        // Verify user is artisan
        if (request()->user()->id !== $milestone->project->artisan_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($milestone->status !== 'pending') {
            return response()->json(['error' => 'Cannot delete started milestone'], 400);
        }

        $milestone->delete();

        return response()->json(['message' => 'Milestone deleted successfully']);
    }
}
