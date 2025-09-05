<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {

            $table->renameColumn('vendor_id', 'actor_id');
            $table->renameColumn('farm_id', 'user_id');
            $table->unsignedBigInteger('pre_order_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->renameColumn('vendor_id', 'actor_id');
            $table->renameColumn('farm_id', 'user_id');

            $table->foreignId('pre_order_id')
                  ->nullable()
                  ->constrained('pre_orders')
                  ->onDelete('cascade')
                  ->change();
        });
    }
};
