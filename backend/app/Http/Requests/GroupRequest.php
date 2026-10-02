<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('group') ? $this->user()->can('update', $this->route('group')) : true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'currency' => $this->isMethod('post') ? ['sometimes', Rule::in(['MYR'])] : ['prohibited'],
            'member_names' => $this->isMethod('post') ? ['sometimes', 'array', 'list'] : ['prohibited'],
            'member_names.*' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'created_by' => ['prohibited'],
        ];
    }
}
