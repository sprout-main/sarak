<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'website' => ['nullable', 'url', 'max:255'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'status' => ['nullable', 'string', Rule::in(['approved', 'pending', 'rejected', 'archived'])],
            'hours' => ['nullable', 'array', 'max:7'],
            'hours.*.day' => ['required', 'integer', 'between:0,6', 'distinct'],
            'hours.*.is_closed' => ['sometimes', 'boolean'],
            'hours.*.opens' => ['nullable', 'date_format:H:i', 'prohibited_if:hours.*.is_closed,true'],
            'hours.*.closes' => ['nullable', 'date_format:H:i', 'prohibited_if:hours.*.is_closed,true'],
            'hours.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $hours = $this->input('hours');

                if (! is_array($hours)) {
                    return;
                }

                foreach ($hours as $index => $row) {
                    $closed = $this->boolish($row['is_closed'] ?? false);

                    if ($closed) {
                        continue;
                    }

                    if (empty($row['opens']) || empty($row['closes'])) {
                        $validator->errors()->add(
                            "hours.{$index}",
                            'Provide opening and closing times, or mark the day as closed.',
                        );
                    }
                }
            },
        ];
    }

    private function boolish(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }
}