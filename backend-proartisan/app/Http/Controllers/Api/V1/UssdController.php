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

        // Raccourci direct : *555*RET-42-4821# -> text = "RET-42-4821".
        // L'identifiant de commande et le code secret sont deux valeurs
        // distinctes. L'ancien format « RET-42 » déduisait le code de
        // l'identifiant, ce qui revenait à n'exiger aucun secret.
        if (! empty($text) && ! str_contains($text, '*')) {
            $cleaned = strtoupper($text);

            if ($parsed = $this->parseValidationInstruction($cleaned)) {
                return $this->executeValidation($parsed);
            }

            if ($this->looksLikeValidationInstruction($cleaned)) {
                return response(
                    'END Format incomplet. Composez PREFIXE-NoCommande-Code (ex: RET-42-4821).',
                    200
                )->header('Content-Type', 'text/plain');
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
                return response('CON Saisir NoCommande*Code (ex: 42*4821) :', 200)
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
                return response('CON Saisir NoCommande*Code (ex: 42*7390) :', 200)
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
     * Préfixes désignant une prise en charge chez le fournisseur.
     */
    private const PICKUP_PREFIXES = ['RET', 'RETRAIT', 'LIVREUR'];

    /**
     * Découpe une instruction « PREFIXE NoCommande Code ».
     *
     * Les trois éléments sont exigés séparément : le numéro de commande n'est
     * pas secret, seul le code l'est. Les confondre — ce que faisait l'ancien
     * format « RET-42 » — revenait à valider une livraison sans preuve.
     *
     * @return array{type: string, order_id: string, code: string}|null
     */
    private function parseValidationInstruction(string $raw): ?array
    {
        $pattern = '/^(RET|RETRAIT|LIVREUR|REC|RECEPTION)[\s-]+(\d+)[\s-]+([A-Z0-9-]+)$/i';

        if (! preg_match($pattern, trim($raw), $matches)) {
            return null;
        }

        $prefix = strtoupper($matches[1]);

        return [
            'type' => in_array($prefix, self::PICKUP_PREFIXES, true) ? 'pickup' : 'delivery',
            'order_id' => $matches[2],
            'code' => strtoupper($matches[3]),
        ];
    }

    /**
     * Reconnaît une instruction de validation mal formée, pour répondre par
     * une aide au format plutôt que par un menu inattendu.
     */
    private function looksLikeValidationInstruction(string $raw): bool
    {
        return (bool) preg_match('/^(RET|RETRAIT|LIVREUR|REC|RECEPTION)\b/i', trim($raw));
    }

    /**
     * @param  array{type: string, order_id: string, code: string}  $parsed
     */
    private function executeValidation(array $parsed)
    {
        return $parsed['type'] === 'pickup'
            ? $this->executePickup($parsed['order_id'], $parsed['code'])
            : $this->executeDelivery($parsed['order_id'], $parsed['code']);
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

        $parsed = $this->parseValidationInstruction($cleanedMsg);

        if ($parsed !== null) {
            $orderId = $parsed['order_id'];

            try {
                $order = \App\Models\Order::find($orderId);
                if (! $order) {
                    $reply = "Erreur ProsArtisan: La commande #{$orderId} n'existe pas.";
                } elseif ($parsed['type'] === 'pickup') {
                    app(\App\Services\OrderService::class)->verifyPickup($order, $parsed['code']);
                    $reply = "ProsArtisan: Retrait de la commande #{$orderId} valide avec succes.";
                } else {
                    app(\App\Services\OrderService::class)->verifyDelivery($order, $parsed['code']);
                    $reply = "ProsArtisan: Livraison de la commande #{$orderId} validee avec succes.";
                }
            } catch (\Exception $e) {
                $reply = 'Erreur ProsArtisan: '.$e->getMessage();
            }
        } else {
            // Le code secret figure sur le bon de commande du fournisseur, ou
            // est communiqué par le client à la remise.
            $reply = 'Format SMS incorrect. Utilisez : RET NoCommande Code (prise en charge) '
                .'ou REC NoCommande Code (livraison). Exemple: RET 42 4821';
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
