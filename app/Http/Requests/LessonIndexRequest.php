<?php

namespace App\Http\Requests;

use App\Traits\ApiValidationResponse;
use Illuminate\Foundation\Http\FormRequest;

class LessonIndexRequest extends FormRequest
{
    use ApiValidationResponse;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'module_id' => ['nullable', 'integer', 'exists:modules,id'],
            'track_id' => ['nullable', 'integer', 'exists:tracks,id'],
        ];
    }
}
