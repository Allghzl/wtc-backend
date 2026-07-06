<?php

namespace App\Http\Requests;

use App\Traits\ApiValidationResponse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChallengeUpdateRequest extends FormRequest
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
            'module_id' => ['required', 'exists:modules,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('challenges')->ignore($this->route('challenge'))],
            'type' => ['required', 'string', 'in:multiple_choice,fill_blank,code_editor,file_upload,github_submission,docker_project,timed_exam,quiz_group'],
            'content' => ['required', 'string'],
            'metadata' => ['nullable', 'array'],
            'max_score' => ['required', 'integer', 'min:0']
        ];
    }
}
