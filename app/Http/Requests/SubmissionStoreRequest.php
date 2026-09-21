<?php

namespace App\Http\Requests;

use App\Traits\ApiValidationResponse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubmissionStoreRequest extends FormRequest
{
    use ApiValidationResponse;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'submitted_content' => [
                'nullable',
                'array',
                'max:200', // cap at 200 top-level items
            ],
            'submitted_content.*' => [
                'nullable',
                'max:50000', // cap each value at 50 KB
            ],
            'file' => [
                'nullable',
                'file',
                'mimes:zip,rar',
                'max:512000',
            ],
        ];
    }
}
