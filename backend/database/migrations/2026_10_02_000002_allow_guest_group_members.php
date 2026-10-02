<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
        Schema::table('group_members', function (Blueprint $table) {
            $table->string('contact_email')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('users')->whereNull('email')->orWhereNull('password')->exists()) {
            throw new RuntimeException('Guest identities exist. Restore a pre-migration backup before rolling back this guest-support migration.');
        }
        Schema::table('group_members', function (Blueprint $table) {
            $table->dropColumn('contact_email');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
