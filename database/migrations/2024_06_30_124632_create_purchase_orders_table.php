<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseOrdersTable extends Migration
{
    public function up()
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();

            // Foreign key referencing 'users' table
            $table->foreignId('user_id')->constrained('users', 'id')->onDelete('Cascade');

            // Foreign key referencing 'address' table
            $table->foreignId('address_id')->constrained('addresses', 'id')->onDelete('cascade');

            // Foreign key referencing 'user type' table
            $table->foreignId('sale_type_id')->constrained('sale_types', 'id')->onDelete('cascade');

            $table->string('customer_name');
            $table->enum('status', ['P', 'F', 'S']);
            $table->timestamps();
        });
    }

    public function down()
    {

    }
}
