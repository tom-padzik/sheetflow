<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadImportRequest extends FormRequest
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
        $maxKilobytes = (int) config('sheetflow.max_kilobytes', 20480);

        return [
            'file' => ['required', 'file', 'extensions:csv,txt,xls,xlsx,ods', 'max:'.$maxKilobytes],
            'sheet' => ['nullable', 'string', 'max:255'],
            'delimiter' => ['nullable', 'string', 'max:1'],
        ];
    }
}
