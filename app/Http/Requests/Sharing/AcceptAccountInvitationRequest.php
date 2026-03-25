<?php

namespace App\Http\Requests\Sharing;

use Illuminate\Foundation\Http\FormRequest;

class AcceptAccountInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'min:32'],
        ];
    }
}
