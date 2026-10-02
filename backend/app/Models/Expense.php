<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = ['group_id', 'created_by', 'description', 'amount', 'paid_by', 'split_type', 'expense_date', 'notes'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'expense_date' => 'date:Y-m-d'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function splits(): HasMany
    {
        return $this->hasMany(ExpenseSplit::class)->orderBy('user_id');
    }
}
