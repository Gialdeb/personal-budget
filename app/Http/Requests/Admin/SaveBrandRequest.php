<?php

namespace App\Http\Requests\Admin;

use App\Models\Brand;
use App\Services\TrackedItems\WebsiteLogoService;
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
        return ['source_logo_uuid' => ['nullable', 'uuid'], 'name' => ['required', 'string', 'max:150'], 'aliases' => ['sometimes', 'array', 'max:30'], 'aliases.*' => ['required', 'string', 'max:150', 'distinct:ignore_case'], 'domain' => ['required', 'string', 'max:253'], 'category' => ['nullable', 'string', 'max:100', Rule::exists('brand_categories', 'name')], 'is_published' => ['required', 'boolean'], 'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'], 'fetch_logo' => ['sometimes', 'boolean'], 'remove_logo' => ['sometimes', 'boolean']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            try {
                $url = app(WebsiteLogoService::class)->normalizeUrl($this->string('domain')->toString());
                $host = parse_url($url, PHP_URL_HOST);
                $domain = preg_replace('/^www\./i', '', $host);
                $this->merge(['domain' => $domain]);
            } catch (ValidationException) {
                $validator->errors()->add('domain', 'Inserisci un dominio valido.');

                return;
            }
            $brand = $this->route('brand');
            if ($brand instanceof Brand && $this->filled('source_logo_uuid')) {
                $validator->errors()->add('source_logo_uuid', 'L’importazione è disponibile solo per nuovi marchi.');
            }
            $key = hash('sha256', Brand::normalize($this->string('name')->toString()).'|'.$domain);
            if (Brand::query()->where('identity_key', $key)->when($brand instanceof Brand, fn ($query) => $query->whereKeyNot($brand->id))->exists()) {
                $validator->errors()->add('name', 'Esiste già un marchio con questo nome e dominio.');
            }
            $terms = array_map(Brand::normalize(...), [$this->string('name')->toString(), ...$this->input('aliases', [])]);
            $sameDomain = Brand::query()->where('domain', $domain)->when($brand instanceof Brand, fn ($query) => $query->whereKeyNot($brand->id))->get(['name', 'aliases']);
            foreach ($sameDomain as $existing) {
                if (array_intersect($terms, array_map(Brand::normalize(...), [$existing->name, ...($existing->aliases ?? [])]))) {
                    $validator->errors()->add('name', 'Nome o alias già utilizzato per questo dominio.');
                    break;
                }
            }
            if ((int) $this->filled('source_logo_uuid') + (int) $this->hasFile('logo') + (int) $this->boolean('fetch_logo') + (int) $this->boolean('remove_logo') > 1) {
                $validator->errors()->add('logo', 'Scegli una sola azione per il logo.');
            }
        }];
    }
}
