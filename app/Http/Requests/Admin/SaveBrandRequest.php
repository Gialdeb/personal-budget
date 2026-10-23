<?php

namespace App\Http\Requests\Admin;

use App\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class SaveBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $values = $this->has('aliases') ? [] : ['aliases' => []];
        foreach (['name'] as $field) {
            if (is_string($this->input($field))) {
                $values[$field] = Str::squish($this->input($field));
            }
        }
        if (is_array($this->input('aliases'))) {
            $values['aliases'] = array_map(fn ($alias) => is_string($alias) ? Str::squish($alias) : $alias, $this->input('aliases'));
        }
        $this->merge($values);
    }

    public function rules(): array
    {
        return ['source_logo_uuid' => ['nullable', 'uuid'], 'name' => ['required', 'string', 'max:150'], 'aliases' => ['sometimes', 'array', 'max:30'], 'aliases.*' => ['required', 'string', 'max:150', 'distinct:ignore_case'], 'alternative_domains' => ['sometimes', 'array', 'max:30'], 'alternative_domains.*' => ['required', 'string', 'max:253'], 'domain' => ['required', 'string', 'max:253'], 'category' => ['nullable', 'string', 'max:100', Rule::exists('brand_categories', 'name')], 'is_published' => ['required', 'boolean'], 'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'], 'fetch_logo' => ['sometimes', 'boolean'], 'remove_logo' => ['sometimes', 'boolean']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $domains = [];
            foreach (['domain' => $this->input('domain'), ...collect($this->input('alternative_domains', []))->mapWithKeys(fn ($value, $index) => ['alternative_domains.'.$index => $value])->all()] as $field => $value) {
                try {
                    $normalized = Brand::normalizeDomain($value);
                    if (in_array($normalized, $domains, true)) {
                        $validator->errors()->add($field, __('form_errors.this_domain_has_already_been_added_for_this_brand'));
                    }
                    $domains[$field] = $normalized;
                } catch (ValidationException) {
                    $validator->errors()->add($field, __('form_errors.enter_a_valid_domain'));
                }
            }
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $domain = $domains['domain'];
            unset($domains['domain']);
            $this->merge(['domain' => $domain, 'alternative_domains' => array_values($domains)]);
            $brand = $this->route('brand');
            if ($brand instanceof Brand && $this->filled('source_logo_uuid')) {
                $validator->errors()->add('source_logo_uuid', __('form_errors.import_is_only_available_for_new_brands'));
            }
            $key = hash('sha256', Brand::normalize($this->string('name')->toString()).'|'.$domain);
            if (Brand::query()->where('identity_key', $key)->when($brand instanceof Brand, fn ($query) => $query->whereKeyNot($brand->id))->exists()) {
                $validator->errors()->add('name', __('form_errors.a_brand_with_this_name_and_domain_already_exists'));
            }
            if (Brand::conflictingDomains([$domain, ...array_values($domains)], $brand instanceof Brand ? $brand : null)) {
                $validator->errors()->add('domain', __('form_errors.a_domain_is_already_associated_with_another_brand'));
            }
            if ((int) $this->filled('source_logo_uuid') + (int) $this->hasFile('logo') + (int) $this->boolean('fetch_logo') + (int) $this->boolean('remove_logo') > 1) {
                $validator->errors()->add('logo', __('form_errors.choose_only_one_action_for_the_logo'));
            }
        }];
    }
}
