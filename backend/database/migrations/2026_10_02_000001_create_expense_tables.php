<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->foreignUlid('created_by')->constrained('users')->restrictOnDelete();
            $table->char('currency', 3)->default('MYR');
            $table->timestamps();
        });
        Schema::create('group_members', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('group_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->restrictOnDelete();
            $table->enum('role', ['owner', 'member']);
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
            $table->unique(['group_id', 'user_id']);
            $table->index(['user_id', 'left_at']);
        });
        Schema::create('expenses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('group_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('paid_by')->constrained('users')->restrictOnDelete();
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->enum('split_type', ['equal', 'custom']);
            $table->date('expense_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['group_id', 'expense_date']);
        });
        Schema::create('expense_splits', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('expense_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->unique(['expense_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        foreach (['expense_splits', 'expenses', 'group_members', 'groups'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
