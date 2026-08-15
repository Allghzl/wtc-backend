<?php

namespace App\Http\Requests;

use App\Traits\ApiValidationResponse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LessonUpdateRequest extends FormRequest
{
    use ApiValidationResponse;
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
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
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('lessons', 'slug')->ignore($this->route('lesson'))],
            'description' => ['nullable', 'sometimes', 'string'],
            'content' => ['sometimes', 'required', 'string'],
            'video_url' => ['nullable', 'sometimes', 'url'],
            'duration' => ['nullable', 'sometimes', 'integer', 'min:1'],
            'order' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::unique('lessons')
                    ->where(fn($query) => $query->where('module_id', $this->module_id ?? $this->route('lesson')->module_id))
                    ->ignore($this->route('lesson')),
            ],
            'module_id' => ['sometimes', 'required', 'exists:modules,id']
        ];
    }
}
