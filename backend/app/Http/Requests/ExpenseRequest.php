<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');
        if (! $this->user()->can('update', $group)) {
            return false;
        }
        if ($id = $this->route('expense')) {
            $expense = $group->expenses()->findOrFail($id);

            return $this->user()->can('update', $expense);
        }

        return true;
    }

    public function rules(): array
    {
        $money = ['required', 'string', 'regex:/\A[0-9]{1,10}(?:\.[0-9]{1,2})?\z/D'];

        return [
            'description' => ['required', 'string', 'max:255'], 'amount' => $money,
            'paid_by' => ['required', 'ulid'], 'expense_date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:10000'], 'split_type' => ['required', Rule::in(['equal', 'custom'])],
            'participant_ids' => ['required', 'array', 'min:1'], 'participant_ids.*' => ['required', 'ulid', 'distinct:strict'],
            'splits' => [Rule::requiredIf($this->input('split_type') === 'custom'), Rule::prohibitedIf($this->input('split_type') !== 'custom'), 'array', 'min:1'],
            'splits.*' => ['array:user_id,amount'], 'splits.*.user_id' => ['required', 'ulid', 'distinct:strict'], 'splits.*.amount' => $money,
            'created_by' => ['prohibited'], 'group_id' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if (Money::cents($this->input('amount')) === 0) {
                $validator->errors()->add('amount', 'The amount must be positive.');
            }
            if ($this->input('split_type') === 'custom') {
                $selected = $this->input('participant_ids');
                $provided = array_column($this->input('splits'), 'user_id');
                sort($selected, SORT_STRING);
                sort($provided, SORT_STRING);
                if ($selected !== $provided) {
                    $validator->errors()->add('splits', 'Custom splits must cover exactly the selected participants.');
                }
                $sum = 0;
                foreach ($this->input('splits') as $split) {
                    $sum = Money::add($sum, Money::cents($split['amount']));
                }
                if ($sum !== Money::cents($this->input('amount'))) {
                    $validator->errors()->add('splits', 'The split amounts must equal the expense amount exactly.');
                }
            }
        }];
    }
}
