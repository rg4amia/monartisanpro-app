<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('driver_id')->nullable()->constrained('users')->onDelete('set null');
            
            $table->string('delivery_mode'); // 'pickup' ou 'delivery'
            $table->string('status')->default('paid'); // 'paid', 'prepared', 'searching_driver', 'driver_assigned', 'driver_picked_up', 'shipping', 'delivered', 'disputed'
            
            $table->unsignedInteger('subtotal'); // Prix des matériaux
            $table->unsignedInteger('delivery_cost')->default(0); // Prix de la livraison
            $table->unsignedInteger('platform_fee'); // Commission plateforme figée à l'achat
            $table->unsignedInteger('total_amount'); // subtotal + delivery_cost + platform_fee
            
            $table->string('pickup_code'); // RETRAIT-XXXX ou LIVREUR-XXXX
            $table->string('reception_code'); // RECEPTION-XXXX
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
