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
        Schema::create('order_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pre_order_id')->constrained('pre_orders')->onDelete('cascade');
            $table->foreignId('farm_id')->constrained('farms')->onDelete('cascade');
            $table->foreignId('crop_id')->nullable()->constrained('crops')->nullOnDelete();
            $table->decimal('fulfilled_qty', 10, 2)->default(0);
            $table->decimal('agreed_price', 10, 2)->nullable();
            $table->string('description')->nullable();
            $table->enum('offer_status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_details');
    }
};
