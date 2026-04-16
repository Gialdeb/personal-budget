<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendPushBroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user !== null && $user->isAdmin();
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:1000'],
            'url' => ['nullable', 'url', 'max:2048'],
            'target_mode' => ['required', 'string', Rule::in(['all', 'single'])],
            'target_user_uuid' => [
                'nullable',
                'uuid',
                'required_if:target_mode,single',
                Rule::exists('users', 'uuid'),
            ],
        ];
    }
}
