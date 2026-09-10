<?php

namespace App\Http\Requests\Api\V1\System;

use Illuminate\Foundation\Http\FormRequest;

class PermissionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'module_code' => ['nullable', 'string', 'max:100'],
            'resource_code' => ['nullable', 'string', 'max:100'],
            'is_enabled' => ['sometimes', 'in:0,1,true,false'],
            'is_system' => ['sometimes', 'in:0,1,true,false'],
        ];
    }
}
