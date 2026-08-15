<?php

namespace App\Http\Requests;

use App\Traits\ApiValidationResponse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrackUpdateRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('tracks', 'slug')->ignore($this->track),
            ],
            'description' => ['nullable', 'sometimes', 'string'],
            'order' => ['sometimes', 'nullable', 'integer', Rule::unique('tracks', 'order')->ignore($this->track)],
            'image_url' => ['nullable', 'sometimes', 'url', 'max:255'],
        ];
    }
}
