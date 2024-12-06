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
        // Add soft deletes to user_types table
        Schema::table('user_types', function (Blueprint $table) {
            $table->softDeletes(); // Adds the deleted_at column
        });

        // Add soft deletes to categories table
        Schema::table('categories', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to sale_types table
        Schema::table('sale_types', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove soft deletes from user_types table
        Schema::table('user_types', function (Blueprint $table) {
            $table->dropSoftDeletes(); // Drops the deleted_at column
        });

        // Remove soft deletes from categories table
        Schema::table('categories', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove soft deletes from sale_types table
        Schema::table('sale_types', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
