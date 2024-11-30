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
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_product_id') // Corrected column name to delivery_product_id
                  ->constrained('delivery_products', 'id')
                  ->onDelete('cascade');
            $table->enum('status', ['NR', 'P', 'S'])->default('NR'); // Adding status column as requested
            $table->string('reason')->nullable(); // Made reason optional
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
