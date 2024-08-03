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
        Schema::create('product_restock_orders', function (Blueprint $table) {
            $table->id();

            // Start Users FK
            $table->foreignId('user_id')->constrained('users', 'id')->onDelete('cascade');

            // Start Stocks FK
            $table->foreignId('product_id')->constrained('products', 'id')->onDelete('cascade');

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
