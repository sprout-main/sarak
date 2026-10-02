<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RunImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source' => ['required', 'string', Rule::in(['overpass', 'official_list', 'nominatim', 'manual'])],
            'payload' => ['required', 'string', 'max:20000'],
        ];
    }
}