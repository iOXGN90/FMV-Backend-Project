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
            // Drop the foreign key constraint
            $table->dropForeign(['delivery_products_id']);
            // Drop the column itself
            $table->dropColumn('delivery_products_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('damages', function (Blueprint $table) {
            // Re-add the column and foreign key in case of rollback
            $table->foreignId('delivery_products_id')
                  ->nullable()
                  ->constrained('delivery_products', 'id')
                  ->onDelete('cascade');
        });
    }
};
