<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->user_id, 'name' => $this->user->name,
            'email' => $this->contact_email ?? $this->user->email,
            'guest' => $this->user->password === null && $this->account_user_id === null,
            'account_user_id' => $this->account_user_id ?? ($this->role === 'owner' ? $this->user_id : null),
            'role' => $this->role, 'active' => $this->left_at === null,
        ];
    }
}
