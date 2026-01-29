<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('transactions')
            ->select([
                'transactions.*',
                'from_user.email as from_email',
                'to_user.email as to_email',
            ])
            ->leftJoin('users as from_user', 'transactions.from_user_id', '=', 'from_user.id')
            ->leftJoin('users as to_user', 'transactions.to_user_id', '=', 'to_user.id');

        // Apply filters
        if ($request->filled('type')) {
            $query->where('transactions.type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('transactions.status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transactions.created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transactions.created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('from_user.email', 'like', "%{$search}%")
                    ->orWhere('to_user.email', 'like', "%{$search}%")
                    ->orWhere('transactions.mobile_money_reference', 'like', "%{$search}%");
            });
        }

        $transactions = $query->orderBy('transactions.created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Get statistics
        $stats = $this->getTransactionStats();

        return Inertia::render('Backoffice/Transactions/Index', [
            'transactions' => $transactions,
            'filters' => $request->only(['type', 'status', 'date_from', 'date_to', 'search']),
            'stats' => $stats,
            'transactionTypes' => $this->getTransactionTypes(),
            'transactionStatuses' => $this->getTransactionStatuses(),
        ]);
    }

    public function show($id)
    {
        $transaction = DB::table('transactions')
            ->select([
                'transactions.*',
                'from_user.email as from_email',
                'to_user.email as to_email',
            ])
            ->leftJoin('users as from_user', 'transactions.from_user_id', '=', 'from_user.id')
            ->leftJoin('users as to_user', 'transactions.to_user_id', '=', 'to_user.id')
            ->where('transactions.id', $id)
            ->first();

        if (!$transaction) {
            abort(404);
        }

        return Inertia::render('Backoffice/Transactions/Show', [
            'transaction' => $transaction,
        ]);
    }

    public function jetons(Request $request)
    {
        $query = DB::table('jetons_materiel')
            ->select([
                'jetons_materiel.*',
                'users.email as artisan_email',
                'sequestres.mission_id',
            ])
            ->leftJoin('users', 'jetons_materiel.artisan_id', '=', 'users.id')
            ->leftJoin('sequestres', 'jetons_materiel.sequestre_id', '=', 'sequestres.id');

        // Apply filters
        if ($request->filled('status')) {
            $query->where('jetons_materiel.status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('jetons_materiel.code', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        $jetons = $query->orderBy('jetons_materiel.created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Get statistics
        $jetonStats = $this->getJetonStats();

        return Inertia::render('Backoffice/Transactions/Jetons', [
            'jetons' => $jetons,
            'filters' => $request->only(['status', 'search']),
            'stats' => $jetonStats,
            'jetonStatuses' => $this->getJetonStatuses(),
        ]);
    }

    public function sequestres(Request $request)
    {
        $query = DB::table('sequestres')
            ->select([
                'sequestres.*',
                'missions.description as mission_description',
                'client.email as client_email',
                'artisan.email as artisan_email',
            ])
            ->leftJoin('missions', 'sequestres.mission_id', '=', 'missions.id')
            ->leftJoin('users as client', 'sequestres.client_id', '=', 'client.id')
            ->leftJoin('users as artisan', 'sequestres.artisan_id', '=', 'artisan.id');

        // Apply filters
        if ($request->filled('status')) {
            $query->where('sequestres.status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('missions.description', 'like', "%{$search}%")
                    ->orWhere('client.email', 'like', "%{$search}%")
                    ->orWhere('artisan.email', 'like', "%{$search}%");
            });
        }

        $sequestres = $query->orderBy('sequestres.created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Get statistics
        $sequestreStats = $this->getSequestreStats();

        return Inertia::render('Backoffice/Transactions/Sequestres', [
            'sequestres' => $sequestres,
            'filters' => $request->only(['status', 'search']),
            'stats' => $sequestreStats,
            'sequestreStatuses' => $this->getSequestreStatuses(),
        ]);
    }

    public function export(Request $request)
    {
        $query = DB::table('transactions')
            ->select([
                'transactions.*',
                'from_user.email as from_email',
                'to_user.email as to_email',
            ])
            ->leftJoin('users as from_user', 'transactions.from_user_id', '=', 'from_user.id')
            ->leftJoin('users as to_user', 'transactions.to_user_id', '=', 'to_user.id');

        // Apply date filters
        if ($request->filled('date_from')) {
            $query->whereDate('transactions.created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transactions.created_at', '<=', $request->date_to);
        }

        $transactions = $query->orderBy('transactions.created_at', 'desc')->get();

        // Generate CSV
        $filename = 'transactions_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($transactions) {
            $file = fopen('php://output', 'w');

            // CSV headers
            fputcsv($file, [
                'ID Transaction',
                'De (Email)',
                'Vers (Email)',
                'Montant (XOF)',
                'Type',
                'Statut',
                'Référence Mobile Money',
                'Description',
                'Date de création',
                'Date de completion',
            ]);

            // CSV data
            foreach ($transactions as $transaction) {
                fputcsv($file, [
                    $transaction->id,
                    $transaction->from_email,
                    $transaction->to_email,
                    $transaction->amount_centimes / 100,
                    $transaction->type,
                    $transaction->status,
                    $transaction->mobile_money_reference,
                    $transaction->description,
                    $transaction->created_at,
                    $transaction->completed_at,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function getTransactionStats()
    {
        return [
            'total_transactions' => DB::table('transactions')->count(),
            'completed_transactions' => DB::table('transactions')->where('status', 'COMPLETED')->count(),
            'pending_transactions' => DB::table('transactions')->where('status', 'PENDING')->count(),
            'failed_transactions' => DB::table('transactions')->where('status', 'FAILED')->count(),
            'total_volume' => DB::table('transactions')->where('status', 'COMPLETED')->sum('amount_centimes'),
            'today_volume' => DB::table('transactions')
                ->where('status', 'COMPLETED')
                ->whereDate('created_at', today())
                ->sum('amount_centimes'),
        ];
    }

    private function getJetonStats()
    {
        return [
            'total_jetons' => DB::table('jetons_materiel')->count(),
            'active_jetons' => DB::table('jetons_materiel')->where('status', 'ACTIVE')->count(),
            'used_jetons' => DB::table('jetons_materiel')->where('status', 'USED')->count(),
            'expired_jetons' => DB::table('jetons_materiel')->where('status', 'EXPIRED')->count(),
            'total_value' => DB::table('jetons_materiel')->sum('total_amount_centimes'),
            'used_value' => DB::table('jetons_materiel')->sum('used_amount_centimes'),
        ];
    }

    private function getSequestreStats()
    {
        return [
            'total_sequestres' => DB::table('sequestres')->count(),
            'blocked_sequestres' => DB::table('sequestres')->where('status', 'BLOCKED')->count(),
            'released_sequestres' => DB::table('sequestres')->where('status', 'RELEASED')->count(),
            'refunded_sequestres' => DB::table('sequestres')->where('status', 'REFUNDED')->count(),
            'total_amount' => DB::table('sequestres')->sum('total_amount_centimes'),
            'materials_released' => DB::table('sequestres')->sum('materials_released_centimes'),
            'labor_released' => DB::table('sequestres')->sum('labor_released_centimes'),
        ];
    }

    private function getTransactionTypes()
    {
        return [
            ['value' => 'DEPOSIT', 'label' => 'Dépôt'],
            ['value' => 'WITHDRAWAL', 'label' => 'Retrait'],
            ['value' => 'ESCROW_RELEASE', 'label' => 'Libération séquestre'],
            ['value' => 'REFUND', 'label' => 'Remboursement'],
            ['value' => 'JETON_PURCHASE', 'label' => 'Achat jeton'],
        ];
    }

    private function getTransactionStatuses()
    {
        return [
            ['value' => 'PENDING', 'label' => 'En attente'],
            ['value' => 'COMPLETED', 'label' => 'Terminé'],
            ['value' => 'FAILED', 'label' => 'Échoué'],
            ['value' => 'CANCELLED', 'label' => 'Annulé'],
        ];
    }

    private function getJetonStatuses()
    {
        return [
            ['value' => 'ACTIVE', 'label' => 'Actif'],
            ['value' => 'USED', 'label' => 'Utilisé'],
            ['value' => 'EXPIRED', 'label' => 'Expiré'],
            ['value' => 'CANCELLED', 'label' => 'Annulé'],
        ];
    }

    private function getSequestreStatuses()
    {
        return [
            ['value' => 'BLOCKED', 'label' => 'Bloqué'],
            ['value' => 'RELEASED', 'label' => 'Libéré'],
            ['value' => 'REFUNDED', 'label' => 'Remboursé'],
            ['value' => 'FROZEN', 'label' => 'Gelé'],
        ];
    }
}
