<?php

namespace App\Http\Requests;

use App\Traits\ApiValidationResponse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ChallengeStoreRequest extends FormRequest
{
    use ApiValidationResponse;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'settings' => $this->input('settings') ?? [],
            'metadata' => $this->input('metadata') ?? [],
            'order' => $this->input('order') ?? 0,
            'points' => $this->input('points') ?? 0,
            'allowed_attempts' => $this->input('allowed_attempts'), // NULL = unlimited attempts
        ]);
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
            | Relation
            |--------------------------------------------------------------------------
            */

            'module_id' => [
                'nullable',
                'required_without:lesson_id',
                'prohibits:lesson_id',
                'exists:modules,id',
            ],

            'lesson_id' => [
                'nullable',
                'required_without:module_id',
                'prohibits:module_id',
                'exists:lessons,id',
            ],

            /*
            |--------------------------------------------------------------------------
            | Basic Information
            |--------------------------------------------------------------------------
            */

            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'slug' => [
                'required',
                'string',
                'max:255',
                'unique:challenges,slug',
            ],

            'type' => [
                'required',
                'string',
                'in:multiple_choice,fill_blank,essay,code_editor,file_upload,github_submission,docker_project,timed_exam,quiz_group',
            ],

            'difficulty' => [
                'nullable',
                'string',
                'in:easy,medium,hard',
            ],

            'order' => [
                'nullable',
                'integer',
                'min:0',
            ],

            /*
            |--------------------------------------------------------------------------
            | Content
            |--------------------------------------------------------------------------
            */

            'content' => [
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
                'array',
            ],

            /*
            |--------------------------------------------------------------------------
            | Metadata
            |--------------------------------------------------------------------------
            */

            'metadata' => [
                'nullable',
                'array',
            ],

            /*
            |--------------------------------------------------------------------------
            | Scoring & Attempts
            |--------------------------------------------------------------------------
            */

            'max_score' => [
                'required',
                'integer',
                'min:1',
                'max:100',
            ],

            'points' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'allowed_attempts' => [
                'nullable',
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
