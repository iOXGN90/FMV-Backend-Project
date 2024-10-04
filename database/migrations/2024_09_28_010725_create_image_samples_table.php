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
        Schema::create('image_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('deliveries', 'id')->onDelete('Cascade');
            // $table->foreignId('category_id')->constrained('categories', 'id')->onDelete('Cascade');

            $table->string('url');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_samples');
    }
};
