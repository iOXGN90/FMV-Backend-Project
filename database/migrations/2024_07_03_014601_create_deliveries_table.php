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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            // Start purchase_order FK
            $table->foreignId('purchase_order_id')->constrained('purchase_orders', 'id')->onDelete('cascade');

            // Start users FK
            $table->foreignId('user_id')->constrained('users', 'id')->onDelete('cascade');

            $table->integer('delivery_no');
            $table->string('notes')->nullable();
            $table->enum('status', ['P', 'F', 'S', 'OD']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
