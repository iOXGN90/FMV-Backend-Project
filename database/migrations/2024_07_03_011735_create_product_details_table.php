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
        Schema::create('product_details', function (Blueprint $table) {
            $table->id();

        // Start FK Stock
            $table->foreignId('product_id')->constrained('products', 'id')->onDelete('cascade');
        // Start FK Stock

        // Start FK Purchase_order
            $table->foreignId('purchase_order_id')->constrained('purchase_orders', 'id')->onDelete('cascade');
        // End FK Purchase_order

            $table->double('price');
            $table->integer('quantity');
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
