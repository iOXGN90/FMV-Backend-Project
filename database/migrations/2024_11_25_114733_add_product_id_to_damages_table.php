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
        Schema::table('damages', function (Blueprint $table) {
            // Add the product_id column as a foreign key
            $table->foreignId('product_id')
                  ->after('delivery_products_id') // Position it after delivery_products_id
                  ->nullable() // If you want to allow nulls initially
                  ->constrained('products', 'id')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('damages', function (Blueprint $table) {
            // Drop the foreign key and column
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });
    }
};
