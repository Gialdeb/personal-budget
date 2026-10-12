<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcceptAdminInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ! app('impersonate')->isImpersonating();
    }

    public function rules(): array
    {
        return ['otp' => ['required', 'string', 'regex:/^[0-9]{6}$/']];
    }
}
