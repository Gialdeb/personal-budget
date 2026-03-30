<?php

namespace App\Http\Requests\Sharing;

use App\Enums\AccountMembershipRoleEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class InviteUserToAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'role' => ['required', new Enum(AccountMembershipRoleEnum::class)],
            'permissions' => ['nullable', 'array'],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
