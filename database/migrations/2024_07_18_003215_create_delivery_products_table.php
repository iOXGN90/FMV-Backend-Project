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
        Schema::create('delivery_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('deliveries', 'id')->onDelete('cascade');
            $table->foreignId('product_details_id')->constrained('product_details', 'id')->onDelete('cascade');
            $table->integer('quantity');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_products');
    }
};
