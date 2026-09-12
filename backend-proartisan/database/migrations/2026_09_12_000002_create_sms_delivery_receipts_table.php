<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accusés de réception (DLR) des SMS envoyés via l'API SMSpro.
 *
 * L'intérêt premier est la fiabilité des OTP : depuis le verrouillage à cinq
 * tentatives, un code non reçu pousse l'utilisateur à en redemander un, et
 * chaque renvoi est une occasion de plus pour un attaquant. Savoir qu'un OTP
 * est parti en `Rejected` plutôt qu'en `Delivered` est donc une donnée de
 * sécurité, pas seulement de confort.
 *
 * `uid` est l'identifiant canonique du message chez SMSpro : il porte la
 * contrainte d'unicité, ce qui rend le traitement idempotent face aux
 * relances (jusqu'à 3, à 10 s / 60 s / 300 s).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_delivery_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 100)->unique();
            $table->string('message_id', 100)->nullable()->index();
            $table->string('recipient', 32)->index();
            $table->string('sender_id', 64)->nullable();
            // 'plain' ou 'otp' : permet d'isoler les échecs de livraison des
            // codes de vérification sans corréler avec nos propres envois.
            $table->string('sms_type', 32)->nullable()->index();
            // Vocabulaire tiers (Delivered, Undelivered, Expired, Enroute,
            // Rejected, Accepted, Failed) : stocké en chaîne plutôt qu'en ENUM,
            // pour ne pas exiger une migration si SMSpro en ajoute un.
            $table->string('status', 32)->index();
            $table->string('campaign_id', 100)->nullable();
            // dateTime et non timestamp : NOT NULL sans DEFAULT échoue en
            // mode strict sur MySQL 5.7.
            $table->dateTime('status_at');
            $table->json('payload')->nullable();
            $table->timestamps();

            // Requête type de l'observabilité : « échecs OTP récents ».
            $table->index(['sms_type', 'status', 'status_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_delivery_receipts');
    }
};
