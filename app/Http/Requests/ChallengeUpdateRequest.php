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
        return $this->user()?->hasRole('admin') || $this->user()?->hasRole('teacher');
    }

    protected function prepareForValidation(): void
    {
        // Only merge defaults for fields that are NOT present in request
        // This allows nullable fields (like allowed_attempts) to be explicitly set to null
        $mergeData = [];

        if (!$this->has('settings')) {
            $mergeData['settings'] = $this->route('challenge')->settings ?? [];
        }

        if (!$this->has('metadata')) {
            $mergeData['metadata'] = $this->route('challenge')->metadata ?? [];
        }

        if (!$this->has('order')) {
            $mergeData['order'] = $this->route('challenge')->order;
        }

        if (!$this->has('points')) {
            $mergeData['points'] = $this->route('challenge')->points;
        }

        if (!$this->has('allowed_attempts')) {
            $mergeData['allowed_attempts'] = $this->route('challenge')->allowed_attempts;
        }

        $this->merge($mergeData);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Relation - Allow partial update (sometimes)
            |--------------------------------------------------------------------------
            */

            'module_id' => [
                'nullable',
                'sometimes',
                'required_without:lesson_id',
                'prohibits:lesson_id',
                'exists:modules,id',
            ],

            'lesson_id' => [
                'nullable',
                'sometimes',
                'required_without:module_id',
                'prohibits:module_id',
                'exists:lessons,id',
            ],

            /*
            |--------------------------------------------------------------------------
            | Basic Information - Use 'sometimes' for partial PATCH support
            |--------------------------------------------------------------------------
            */

            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('challenges')->ignore($this->route('challenge')),
            ],

            'type' => [
                'sometimes',
                'required',
                'string',
                'in:multiple_choice,fill_blank,essay,code_editor,file_upload,github_submission,docker_project,timed_exam,quiz_group',
            ],

            'difficulty' => [
                'nullable',
                'sometimes',
                'string',
                'in:easy,medium,hard',
            ],

            'order' => [
                'nullable',
                'sometimes',
                'integer',
                'min:0',
            ],

            /*
            |--------------------------------------------------------------------------
            | Content
            |--------------------------------------------------------------------------
            */

            'content' => [
                'sometimes',
                'required',
                'string',
            ],

            /*
            |--------------------------------------------------------------------------
            | Challenge Configuration
            |--------------------------------------------------------------------------
            */

            'settings' => [
                'nullable',
                'sometimes',
                'array',
            ],

            /*
            |--------------------------------------------------------------------------
            | Metadata
            |--------------------------------------------------------------------------
            */

            'metadata' => [
                'nullable',
                'sometimes',
                'array',
            ],

            /*
            |--------------------------------------------------------------------------
            | Scoring & Attempts
            |--------------------------------------------------------------------------
            */

            'max_score' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'max:100',
            ],

            'points' => [
                'nullable',
                'sometimes',
                'integer',
                'min:0',
            ],

            'allowed_attempts' => [
                'nullable',
                'sometimes',
                'integer',
                'min:1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'module_id.required_without' =>
            'Pilih salah satu: Challenge harus terikat ke Module atau Lesson.',

            'module_id.prohibits' =>
            'Challenge tidak boleh terikat ke Module dan Lesson sekaligus.',

            'lesson_id.required_without' =>
            'Pilih salah satu: Challenge harus terikat ke Module atau Lesson.',

            'lesson_id.prohibits' =>
            'Challenge tidak boleh terikat ke Module dan Lesson sekaligus.',

            'allowed_attempts.min' =>
            'Jumlah percobaan minimal adalah 1. Kosongkan untuk unlimited attempts.',
        ];
    }
}
