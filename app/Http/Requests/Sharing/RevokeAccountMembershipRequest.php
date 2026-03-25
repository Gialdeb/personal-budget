<?php

namespace App\Http\Requests\Sharing;

use Illuminate\Foundation\Http\FormRequest;

class RevokeAccountMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
