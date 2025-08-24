<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_orders', function (Blueprint $table) {
            $table->string('input_product_name')->nullable()->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('pre_orders', function (Blueprint $table) {
            $table->dropColumn('input_product_name');
        });
    }
};
