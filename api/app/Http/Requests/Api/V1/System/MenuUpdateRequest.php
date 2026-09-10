<?php

namespace App\Http\Requests\Api\V1\System;

use App\Models\Menu;
use App\Services\MenuTreeBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MenuUpdateRequest extends FormRequest
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
        /** @var Menu $menu */
        $menu = $this->route('menu');

        return [
            'parent_id' => ['sometimes', 'nullable', 'integer', Rule::exists('menus', 'id')],
            'title' => ['sometimes', 'string', 'max:80'],
            'name' => ['sometimes', 'nullable', 'string', 'max:120', 'regex:/^[A-Za-z][A-Za-z0-9_.-]*$/', Rule::unique('menus', 'name')->ignore($menu->id)],
            'path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'component' => ['sometimes', 'nullable', 'string', 'max:120', 'regex:/^[A-Za-z][A-Za-z0-9_.-]*$/'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:80', 'regex:/^[A-Za-z][A-Za-z0-9_.-]*$/'],
            'permission_name' => ['sometimes', 'nullable', 'string', 'max:255', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'is_visible' => ['sometimes', 'boolean'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->has('parent_id') || $this->input('parent_id') === null) {
                    return;
                }

                /** @var Menu $menu */
                $menu = $this->route('menu');
                $parent = Menu::query()->find($this->integer('parent_id'));

                if (! $parent instanceof Menu) {
                    return;
                }

                if ($parent->is($menu) || MenuTreeBuilder::isDescendantOf($parent, $menu)) {
                    $validator->errors()->add('parent_id', 'The parent menu cannot be itself or its descendant.');
                }
            },
        ];
    }
}
