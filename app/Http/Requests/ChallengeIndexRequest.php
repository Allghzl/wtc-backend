<?php

namespace App\Http\Requests;

use App\Traits\ApiValidationResponse;
use Illuminate\Foundation\Http\FormRequest;

class ChallengeIndexRequest extends FormRequest
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
            'lesson_id' => ['nullable', 'integer', 'exists:lessons,id'],
            'track_id' => ['nullable', 'integer', 'exists:tracks,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'module_id.exists' => 'The selected module does not exist.',
            'lesson_id.exists' => 'The selected lesson does not exist.',
            'track_id.exists' => 'The selected track does not exist.',
        ];
    }
}
