<?php

namespace App\Http\Requests\Sharing;

use App\Enums\AccountMembershipRoleEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateAccountMembershipRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => [
                'required',
                new Enum(AccountMembershipRoleEnum::class),
                Rule::in([
                    AccountMembershipRoleEnum::VIEWER->value,
                    AccountMembershipRoleEnum::EDITOR->value,
                ]),
            ],
        ];
    }
}
