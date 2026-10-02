<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MergeQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'into_place_id' => ['required', 'integer', 'exists:places,id'],
        ];
    }
}