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
        Schema::create('damages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('deliveries', 'id')->onDelete('cascade');
            $table->foreignId('delivery_products_id')->constrained('delivery_products', 'id')->onDelete('cascade');
            $table->integer('no_of_damages');
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
