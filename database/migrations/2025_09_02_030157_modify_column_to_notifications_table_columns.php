<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // public function up(): void
    // {
    //     Schema::table('notifications', function (Blueprint $table) {

    //         $table->dropForeign(['actor_id']);
    //         $table->dropForeign(['user_id']);

    //         // Rename columns
    //         $table->renameColumn('actor_id', 'vendor_id');
    //         $table->renameColumn('user_id', 'farm_id');
    //     });

    //     Schema::table('notifications', function (Blueprint $table) {

    //         $table->foreign('vendor_id')
    //             ->references('id')->on('vendors')
    //             ->onDelete('cascade');

    //         $table->foreign('farm_id')
    //             ->references('id')->on('farms')
    //             ->onDelete('cascade');
    //     });
    // }

    // public function down(): void
    // {
    //     Schema::table('notifications', function (Blueprint $table) {
    //         $table->dropForeign(['vendor_id']);
    //         $table->dropForeign(['farm_id']);

    //         $table->renameColumn('vendor_id', 'actor_id');
    //         $table->renameColumn('farm_id', 'user_id');

    //         $table->foreign('actor_id')
    //             ->references('id')->on('users')
    //             ->onDelete('cascade');

    //         $table->foreign('user_id')
    //             ->references('id')->on('users')
    //             ->onDelete('cascade');
    //     });
    // }
};
