<?php

namespace App\Http\Requests;

use App\Traits\ApiValidationResponse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LessonStoreRequest extends FormRequest
{
    use ApiValidationResponse;
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && $user->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'slug' => ['required', 'string'],
            'video_url' => ['nullable', 'url'],
            'order' => [
                'required',
                'integer',
                Rule::unique('lessons')
                    ->where(fn($query) => $query->where('module_id', $this->module_id)),
            ],
            'module_id' => ['required', 'exists:modules,id']
        ];
    }
}
