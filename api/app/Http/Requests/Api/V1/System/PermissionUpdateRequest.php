<?php

namespace App\Http\Requests\Api\V1\System;

use App\Models\Permission;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PermissionUpdateRequest extends FormRequest
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
        /** @var Permission $permission */
        $permission = $this->route('permission');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-z][a-z0-9]*(\.[a-z][a-z0-9-]*)+$/',
                function (string $attribute, mixed $value, Closure $fail) use ($permission): void {
                    if (str_starts_with((string) $value, 'system.') && $value !== $permission->name) {
                        $fail('The system permission namespace is reserved.');
                    }
                },
                Rule::unique('permissions', 'name')->where('guard_name', 'web')->ignore($permission->id),
            ],
            'title' => ['sometimes', 'nullable', 'string', 'max:80'],
            'module_code' => ['sometimes', 'nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9-]*$/'],
            'module_title' => ['sometimes', 'nullable', 'string', 'max:80'],
            'resource_code' => ['sometimes', 'nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9-]*$/'],
            'resource_title' => ['sometimes', 'nullable', 'string', 'max:80'],
            'action_code' => ['sometimes', 'nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9-]*$/'],
            'action_title' => ['sometimes', 'nullable', 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
