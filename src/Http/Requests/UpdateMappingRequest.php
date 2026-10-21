<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMappingRequest extends FormRequest
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
            'column_map' => ['required', 'array'],
            'column_map.*' => ['nullable', 'string', 'max:255'],
            'save_template' => ['sometimes', 'boolean'],
            'template_name' => ['nullable', 'string', 'max:255'],
            'invalid_actions' => ['sometimes', 'array'],
            'invalid_actions.*' => ['nullable', 'string', 'max:32'],
        ];
    }
}
