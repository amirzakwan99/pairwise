<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->string('invite_token_hash', 64)->nullable()->unique();
            $table->timestamp('invite_expires_at')->nullable();
        });
        Schema::table('group_members', function (Blueprint $table) {
            $table->foreignUlid('account_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unique(['group_id', 'account_user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->dropUnique(['group_id', 'account_user_id']);
            $table->dropConstrainedForeignId('account_user_id');
        });
        Schema::table('groups', function (Blueprint $table) {
            $table->dropUnique(['invite_token_hash']);
            $table->dropColumn(['invite_token_hash', 'invite_expires_at']);
        });
    }
};
