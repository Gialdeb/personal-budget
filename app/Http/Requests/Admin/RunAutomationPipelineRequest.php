<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RunAutomationPipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('admin');
        }

        return (bool) ($user->is_admin ?? false);
    }

    public function rules(): array
    {
        return [
            'reference_date' => ['nullable', 'date'],
        ];
    }
}
