<?php

namespace App\Http\Requests\Api\V1\System;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MenuStoreRequest extends FormRequest
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
            'parent_id' => ['nullable', 'integer', Rule::exists('menus', 'id')],
            'title' => ['required', 'string', 'max:80'],
            'name' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z][A-Za-z0-9_.-]*$/', Rule::unique('menus', 'name')],
            'path' => ['nullable', 'string', 'max:255'],
            'component' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z][A-Za-z0-9_.-]*$/'],
            'icon' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z][A-Za-z0-9_.-]*$/'],
            'permission_name' => ['nullable', 'string', 'max:255', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'is_visible' => ['sometimes', 'boolean'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
