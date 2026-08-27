<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UssdController extends Controller
{
    /**
     * Recherche d'un utilisateur par son numéro de téléphone normalisé.
     */
    private function findUserByPhone(string $rawPhone)
    {
        $smsService = app(\App\Services\SmsService::class);
        $normalized = $smsService->normalizePhone($rawPhone); // ex: 2250506070809
        $withPlus = '+' . $normalized; // ex: +2250506070809
        
        return \App\Models\User::where('phone', $withPlus)
            ->orWhere('phone', $normalized)
            ->orWhere('phone', $rawPhone)
            ->first();
    }

    /**
     * Endpoint d'interaction USSD (passerelle USSD).
     */
    public function handle(Request $request)
    {
        $rawPhone = $request->input('phoneNumber') 
            ?? $request->input('phone') 
            ?? $request->input('MSISDN');
        $text = $request->input('text') ?? '';
        $sessionId = $request->input('sessionId');

        if (!$rawPhone) {
            return response("END Erreur: Numero de telephone manquant.", 200)
                ->header('Content-Type', 'text/plain');
        }

        // Trouver l'utilisateur et valider le rôle
        $user = $this->findUserByPhone($rawPhone);
        if (!$user) {
            return response("END Numero non enregistre sur ProsArtisan.", 200)
                ->header('Content-Type', 'text/plain');
        }

        if ($user->role !== 'livreur' && $user->role !== 'driver' && $user->role !== 'admin') {
            return response("END Acces refuse. Role livreur requis.", 200)
                ->header('Content-Type', 'text/plain');
        }

        $text = trim($text);
        
        // Raccourci Direct (ex: dial *555*RET-123# -> text = "RET-123")
        if (!empty($text) && !str_contains($text, '*')) {
            $cleaned = strtoupper($text);
            if (str_starts_with($cleaned, 'RET-') 
                || str_starts_with($cleaned, 'REC-')
                || str_starts_with($cleaned, 'RETRAIT-')
                || str_starts_with($cleaned, 'RECEPTION-')
                || str_starts_with($cleaned, 'LIVREUR-')
            ) {
                return $this->processDirectCode($cleaned);
            }
        }

        $parts = $text === '' ? [] : explode('*', $text);
        $step = count($parts);

        // Menu interactif USSD
        if ($step === 0) {
            $menu = "CON ProsArtisan Logistique\n"
                  . "1. Valider Retrait (Pickup)\n"
                  . "2. Valider Livraison (Delivery)";
            return response($menu, 200)->header('Content-Type', 'text/plain');
        }

        $choice = $parts[0];

        if ($choice === '1') {
            // Prise en charge / Retrait
            if ($step === 1) {
                return response("CON Saisir ID_Commande*Code (ex: 42*RET-42) :", 200)
                    ->header('Content-Type', 'text/plain');
            }
            if ($step === 2) {
                $orderVal = $parts[1];
                return response("CON Saisir le code de retrait pour la commande #" . $orderVal . " :", 200)
                    ->header('Content-Type', 'text/plain');
            }
            if ($step === 3) {
                $orderId = $parts[1];
                $code = $parts[2];
                return $this->executePickup($orderId, $code);
            }
        } elseif ($choice === '2') {
            // Livraison
            if ($step === 1) {
                return response("CON Saisir ID_Commande*Code (ex: 42*REC-42) :", 200)
                    ->header('Content-Type', 'text/plain');
            }
            if ($step === 2) {
                $orderVal = $parts[1];
                return response("CON Saisir le code de reception pour la commande #" . $orderVal . " :", 200)
                    ->header('Content-Type', 'text/plain');
            }
            if ($step === 3) {
                $orderId = $parts[1];
                $code = $parts[2];
                return $this->executeDelivery($orderId, $code);
            }
        }

        return response("END Option invalide. Veuillez reessayer.", 200)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Traitement direct d'un code de validation composé instantanément.
     */
    private function processDirectCode(string $code)
    {
        $numericPart = preg_replace('/[^0-9]/', '', $code);
        if (empty($numericPart)) {
            return response("END Erreur: Code invalide.", 200)
                ->header('Content-Type', 'text/plain');
        }

        if (str_starts_with($code, 'RET-') 
            || str_starts_with($code, 'RETRAIT-')
            || str_starts_with($code, 'LIVREUR-')
        ) {
            return $this->executePickup($numericPart, $code);
        }

        if (str_starts_with($code, 'REC-') 
            || str_starts_with($code, 'RECEPTION-')
        ) {
            return $this->executeDelivery($numericPart, $code);
        }

        return response("END Erreur: Code non reconnu.", 200)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Valider la récupération de commande.
     */
    private function executePickup(string $orderId, string $code)
    {
        try {
            $order = \App\Models\Order::find($orderId);
            if (!$order) {
                return response("END Erreur: Commande #{$orderId} introuvable.", 200)
                    ->header('Content-Type', 'text/plain');
            }

            app(\App\Services\OrderService::class)->verifyPickup($order, $code);

            return response("END Retrait de la commande #{$orderId} valide avec succes.", 200)
                ->header('Content-Type', 'text/plain');
        } catch (\Exception $e) {
            return response("END Erreur: " . $e->getMessage(), 200)
                ->header('Content-Type', 'text/plain');
        }
    }

    /**
     * Valider la livraison finale.
     */
    private function executeDelivery(string $orderId, string $code)
    {
        try {
            $order = \App\Models\Order::find($orderId);
            if (!$order) {
                return response("END Erreur: Commande #{$orderId} introuvable.", 200)
                    ->header('Content-Type', 'text/plain');
            }

            app(\App\Services\OrderService::class)->verifyDelivery($order, $code);

            return response("END Livraison de la commande #{$orderId} validee avec succes.", 200)
                ->header('Content-Type', 'text/plain');
        } catch (\Exception $e) {
            return response("END Erreur: " . $e->getMessage(), 200)
                ->header('Content-Type', 'text/plain');
        }
    }

    /**
     * Endpoint pour la validation par SMS entrant.
     */
    public function incomingSms(Request $request)
    {
        $from = $request->input('from') ?? $request->input('sender');
        $message = trim($request->input('message') ?? $request->input('text') ?? '');

        if (!$from || empty($message)) {
            return response()->json(['success' => false, 'error' => 'Champs requis manquants.'], 400);
        }

        $user = $this->findUserByPhone($from);
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Numero de telephone non enregistre.'], 404);
        }

        if ($user->role !== 'livreur' && $user->role !== 'driver' && $user->role !== 'admin') {
            return response()->json(['success' => false, 'error' => 'Acces refuse.'], 403);
        }

        $cleanedMsg = strtoupper(trim($message));
        $reply = '';

        if (preg_match('/^(RET|RETRAIT|LIVREUR|REC|RECEPTION)[ -]?(\d+)$/i', $cleanedMsg, $matches)) {
            $prefix = strtoupper($matches[1]);
            $orderId = $matches[2];
            
            if (in_array($prefix, ['RET', 'RETRAIT', 'LIVREUR'])) {
                $code = "RET-{$orderId}";
                $type = 'pickup';
            } else {
                $code = "REC-{$orderId}";
                $type = 'delivery';
            }

            try {
                $order = \App\Models\Order::find($orderId);
                if (!$order) {
                    $reply = "Erreur ProsArtisan: La commande #{$orderId} n'existe pas.";
                } else {
                    if ($type === 'pickup') {
                        app(\App\Services\OrderService::class)->verifyPickup($order, $code);
                        $reply = "ProsArtisan: Retrait de la commande #{$orderId} valide avec succes.";
                    } else {
                        app(\App\Services\OrderService::class)->verifyDelivery($order, $code);
                        $reply = "ProsArtisan: Livraison de la commande #{$orderId} validee avec succes.";
                    }
                }
            } catch (\Exception $e) {
                $reply = "Erreur ProsArtisan: " . $e->getMessage();
            }
        } else {
            $reply = "Format SMS incorrect. Utilisez : RET-ID (prise en charge) ou REC-ID (livraison). Exemple: RET-42";
        }

        // Retourner la réponse par SMS au livreur
        try {
            app(\App\Services\SmsService::class)->send($from, $reply);
        } catch (\Exception $e) {
            Log::error("USSD/SMS Callback: Failed to send SMS reply to {$from}: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'reply' => $reply
        ]);
    }
}
