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
        //
        Schema::create('pre_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('crop_id')->nullable()->constrained('crops')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->decimal('qty', 10, 2);
            $table->string('location')->nullable();
            $table->text('note')->nullable();
            $table->date('delivery_date')->nullable();
            $table->string('recurring_schedule')->nullable();
            $table->enum('status', ['pending', 'partially_fulfilled', 'fulfilled', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::dropIfExists('pre_orders');
    }
};
