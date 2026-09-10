<?php

namespace App\Http\Requests\Api\V1\System;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PermissionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z][a-z0-9]*(\.[a-z][a-z0-9-]*)+$/', 'not_regex:/^system\./', Rule::unique('permissions', 'name')->where('guard_name', 'web')],
            'title' => ['nullable', 'string', 'max:80'],
            'module_code' => ['nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9-]*$/'],
            'module_title' => ['nullable', 'string', 'max:80'],
            'resource_code' => ['nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9-]*$/'],
            'resource_title' => ['nullable', 'string', 'max:80'],
            'action_code' => ['nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9-]*$/'],
            'action_title' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
