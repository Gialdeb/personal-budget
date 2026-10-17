<?php

namespace App\Concerns;

use App\Services\TrackedItems\TrackedItemIdentityService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait CanonicalTrackedItemInputs
{
    /** @var array<string, array<int, string>> */
    private array $canonicalReferenceErrors = [];

    protected function getValidatorInstance(): Validator
    {
        $validator = parent::getValidatorInstance();
        $validator->after(function (Validator $validator): void {
            foreach ($this->canonicalReferenceErrors as $field => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add($field, $message);
                }
            }
        });

        return $validator;
    }

    protected function canonicalizeNewReference(string $uuidField, string $idField, Builder $catalog): void
    {
        $uuid = $this->filled($uuidField) ? $this->input($uuidField) : null;
        $id = $this->filled($idField) ? $this->input($idField) : null;
        if (($uuid !== null && (! is_string($uuid) || ! Str::isUuid($uuid))) || ($id !== null && ! is_numeric($id))) {
            return;
        }
        try {
            $reference = app(TrackedItemIdentityService::class)->forNewOperation($catalog, $uuid, $id === null ? null : (int) $id, $uuid !== null ? $uuidField : $idField);
        } catch (ValidationException $exception) {
            $this->canonicalReferenceErrors = array_merge($this->canonicalReferenceErrors, $exception->errors());

            return;
        }
        if ($reference !== null) {
            $this->merge([$uuidField => $reference->uuid, $idField => $reference->id]);
        }
    }
}
