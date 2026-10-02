<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'group_id' => $this->group_id, 'created_by' => $this->created_by,
            'description' => $this->description, 'amount' => $this->amount, 'paid_by' => $this->paid_by,
            'split_type' => $this->split_type, 'expense_date' => $this->expense_date->format('Y-m-d'), 'notes' => $this->notes,
            'created_at' => $this->created_at->toISOString(), 'updated_at' => $this->updated_at->toISOString(),
            'splits' => $this->splits->map(fn ($split) => ['user_id' => $split->user_id, 'amount' => $split->amount])->all(),
        ];
    }
}
