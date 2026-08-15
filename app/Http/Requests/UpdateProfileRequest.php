<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization logic can be added here if needed
        // For now, allow authenticated users to update profiles
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
            'display_name' => [
                'sometimes',
                'nullable',
                'string',
                'min:2',
                'max:100',
            ],
            'study_class_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:study_classes,id',
            ],
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
            'display_name.string' => 'Display name must be a string.',
            'display_name.min' => 'Display name must be at least 2 characters.',
            'display_name.max' => 'Display name must not exceed 100 characters.',
            'study_class_id.integer' => 'Study class ID must be an integer.',
            'study_class_id.exists' => 'The selected study class does not exist.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'display_name' => 'display name',
            'study_class_id' => 'study class',
        ];
    }
}
