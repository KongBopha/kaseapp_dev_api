<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'password')) {
                $table->string('password')->nullable();  
            }
            if (!Schema::hasColumn('users', 'remember_token')) {
                $table->rememberToken();
            }
            if (!Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable();
            }
        });

        DB::statement("ALTER TABLE users MODIFY role ENUM('consumer','farmer','vendor') NOT NULL DEFAULT 'consumer'");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['password','remember_token','email_verified_at']);
        });
    }
};
