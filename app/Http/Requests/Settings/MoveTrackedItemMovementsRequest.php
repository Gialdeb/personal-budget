<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class MoveTrackedItemMovementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['source_uuid' => ['required', 'uuid'], 'destination_uuid' => ['required', 'uuid', 'different:source_uuid'],
            'preview_token' => [$this->isMethod('POST') ? 'required' : 'nullable', 'string', 'size:64'],
            'confirmed' => [$this->isMethod('POST') ? 'accepted' : 'nullable']];
    }
}
