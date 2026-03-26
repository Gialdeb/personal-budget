<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    protected function prepareForValidation(): void
    {
        $user = $this->user();

        if ($user === null) {
            return;
        }

        $this->merge([
            'name' => $this->input('name', $user->name),
            'surname' => $this->input('surname', $user->surname),
            'email' => $this->input('email', $user->email),
            'format_locale' => $this->input('format_locale', $user->format_locale),
            'avatar_remove' => $this->boolean('avatar_remove'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->user()->id),
            'avatar_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'avatar_remove' => ['nullable', 'boolean'],
        ];
    }
}
