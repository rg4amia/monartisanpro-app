<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Domain\Dispute\Models\ValueObjects\DisputeStatus;
use App\Domain\Dispute\Models\ValueObjects\DecisionType;

class DisputeController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('litiges')
            ->select([
                'litiges.*',
                'missions.description as mission_description',
                'reporter.email as reporter_email',
                'defendant.email as defendant_email',
                'mediator.email as mediator_email'
            ])
            ->leftJoin('missions', 'litiges.mission_id', '=', 'missions.id')
            ->leftJoin('users as reporter', 'litiges.reporter_id', '=', 'reporter.id')
            ->leftJoin('users as defendant', 'litiges.defendant_id', '=', 'defendant.id')
            ->leftJoin('users as mediator', 'litiges.mediator_id', '=', 'mediator.id');

        // Apply filters
        if ($request->filled('status')) {
            $query->where('litiges.status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('litiges.type', $request->type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('litiges.description', 'like', "%{$search}%")
                    ->orWhere('missions.description', 'like', "%{$search}%")
                    ->orWhere('reporter.email', 'like', "%{$search}%")
                    ->orWhere('defendant.email', 'like', "%{$search}%");
            });
        }

        $disputes = $query->orderBy('litiges.created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Backoffice/Disputes/Index', [
            'disputes' => $disputes,
            'filters' => $request->only(['status', 'type', 'search']),
            'disputeStatuses' => $this->getDisputeStatuses(),
            'disputeTypes' => $this->getDisputeTypes(),
        ]);
    }

    public function show($id)
    {
        $dispute = DB::table('litiges')
            ->select([
                'litiges.*',
                'missions.description as mission_description',
                'missions.id as mission_id',
                'reporter.email as reporter_email',
                'defendant.email as defendant_email',
                'mediator.email as mediator_email',
                'sequestres.total_amount_centimes',
                'sequestres.status as sequestre_status'
            ])
            ->leftJoin('missions', 'litiges.mission_id', '=', 'missions.id')
            ->leftJoin('users as reporter', 'litiges.reporter_id', '=', 'reporter.id')
            ->leftJoin('users as defendant', 'litiges.defendant_id', '=', 'defendant.id')
            ->leftJoin('users as mediator', 'litiges.mediator_id', '=', 'mediator.id')
            ->leftJoin('sequestres', 'missions.id', '=', 'sequestres.mission_id')
            ->where('litiges.id', $id)
            ->first();

        if (!$dispute) {
            abort(404);
        }

        // Get mediation communications
        $communications = DB::table('mediation_communications')
            ->select([
                'mediation_communications.*',
                'users.email as sender_email'
            ])
            ->leftJoin('users', 'mediation_communications.sender_id', '=', 'users.id')
            ->where('mediation_communications.litige_id', $id)
            ->orderBy('mediation_communications.created_at', 'asc')
            ->get();

        // Get evidence files
        $evidence = json_decode($dispute->evidence, true) ?? [];

        return Inertia::render('Backoffice/Disputes/Show', [
            'dispute' => $dispute,
            'communications' => $communications,
            'evidence' => $evidence,
            'decisionTypes' => $this->getDecisionTypes(),
        ]);
    }

    public function assignMediator(Request $request, $id)
    {
        $request->validate([
            'mediator_id' => 'required|exists:users,id',
        ]);

        DB::table('litiges')
            ->where('id', $id)
            ->update([
                'mediator_id' => $request->mediator_id,
                'status' => DisputeStatus::IN_MEDIATION->value,
                'updated_at' => now(),
            ]);

        // Log the assignment
        DB::table('user_activity_logs')->insert([
            'user_id' => $request->mediator_id,
            'action' => 'mediation_assigned',
            'details' => json_encode([
                'litige_id' => $id,
                'assigned_by' => auth()->id(),
                'assigned_at' => now(),
            ]),
            'created_at' => now(),
        ]);

        return back()->with('success', 'Médiateur assigné avec succès.');
    }

    public function renderDecision(Request $request, $id)
    {
        $request->validate([
            'decision_type' => 'required|in:REFUND_CLIENT,PAY_ARTISAN,PARTIAL_REFUND,FREEZE_FUNDS',
            'amount_centimes' => 'nullable|integer|min:0',
            'justification' => 'required|string|max:1000',
        ]);

        DB::beginTransaction();

        try {
            // Update dispute status
            DB::table('litiges')
                ->where('id', $id)
                ->update([
                    'status' => DisputeStatus::RESOLVED->value,
                    'decision_type' => $request->decision_type,
                    'decision_amount_centimes' => $request->amount_centimes,
                    'decision_justification' => $request->justification,
                    'resolved_at' => now(),
                    'updated_at' => now(),
                ]);

            // Execute the decision on the sequestre
            $dispute = DB::table('litiges')
                ->leftJoin('missions', 'litiges.mission_id', '=', 'missions.id')
                ->leftJoin('sequestres', 'missions.id', '=', 'sequestres.mission_id')
                ->where('litiges.id', $id)
                ->select('sequestres.id as sequestre_id', 'litiges.*')
                ->first();

            if ($dispute && $dispute->sequestre_id) {
                $this->executeDecision($dispute->sequestre_id, $request->decision_type, $request->amount_centimes);
            }

            // Log the decision
            DB::table('user_activity_logs')->insert([
                'user_id' => auth()->id(),
                'action' => 'arbitration_rendered',
                'details' => json_encode([
                    'litige_id' => $id,
                    'decision_type' => $request->decision_type,
                    'amount_centimes' => $request->amount_centimes,
                    'justification' => $request->justification,
                    'rendered_at' => now(),
                ]),
                'created_at' => now(),
            ]);

            DB::commit();

            return back()->with('success', 'Décision d\'arbitrage rendue avec succès.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Erreur lors du rendu de la décision: ' . $e->getMessage()]);
        }
    }

    public function addCommunication(Request $request, $id)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        DB::table('mediation_communications')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'litige_id' => $id,
            'sender_id' => auth()->id(),
            'message' => $request->message,
            'created_at' => now(),
        ]);

        return back()->with('success', 'Message ajouté à la médiation.');
    }

    private function executeDecision($sequestreId, $decisionType, $amountCentimes = null)
    {
        switch ($decisionType) {
            case DecisionType::REFUND_CLIENT->value:
                DB::table('sequestres')
                    ->where('id', $sequestreId)
                    ->update(['status' => 'REFUNDED']);
                break;

            case DecisionType::PAY_ARTISAN->value:
                DB::table('sequestres')
                    ->where('id', $sequestreId)
                    ->update(['status' => 'RELEASED']);
                break;

            case DecisionType::PARTIAL_REFUND->value:
                // Implement partial refund logic
                break;

            case DecisionType::FREEZE_FUNDS->value:
                DB::table('sequestres')
                    ->where('id', $sequestreId)
                    ->update(['status' => 'FROZEN']);
                break;
        }
    }

    private function getDisputeStatuses()
    {
        return [
            ['value' => DisputeStatus::OPEN->value, 'label' => 'Ouvert'],
            ['value' => DisputeStatus::IN_MEDIATION->value, 'label' => 'En médiation'],
            ['value' => DisputeStatus::IN_ARBITRATION->value, 'label' => 'En arbitrage'],
            ['value' => DisputeStatus::RESOLVED->value, 'label' => 'Résolu'],
            ['value' => DisputeStatus::CLOSED->value, 'label' => 'Fermé'],
        ];
    }

    private function getDisputeTypes()
    {
        return [
            ['value' => 'QUALITY', 'label' => 'Qualité'],
            ['value' => 'PAYMENT', 'label' => 'Paiement'],
            ['value' => 'DELAY', 'label' => 'Retard'],
            ['value' => 'OTHER', 'label' => 'Autre'],
        ];
    }

    private function getDecisionTypes()
    {
        return [
            ['value' => DecisionType::REFUND_CLIENT->value, 'label' => 'Rembourser le client'],
            ['value' => DecisionType::PAY_ARTISAN->value, 'label' => 'Payer l\'artisan'],
            ['value' => DecisionType::PARTIAL_REFUND->value, 'label' => 'Remboursement partiel'],
            ['value' => DecisionType::FREEZE_FUNDS->value, 'label' => 'Geler les fonds'],
        ];
    }
}
