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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id(); 
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // recipient
            $table->foreignId('actor_id')->constrained('users')->onDelete('cascade'); // who triggers
            $table->foreignId('pre_order_id')->nullable()->constrained('pre_orders')->onDelete('cascade');
            $table->foreignId('reference_id')->nullable()->constrained('order_details')->onDelete('cascade');
            $table->enum('type', ['pre_order', 'acceptance', 'offer', 'rejection']);
            $table->text('message');
            $table->boolean('read_status')->default(false);
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
