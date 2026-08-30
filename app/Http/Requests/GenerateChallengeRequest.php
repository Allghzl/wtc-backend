<?php

namespace App\Http\Requests;

use App\Traits\ApiValidationResponse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GenerateChallengeRequest extends FormRequest
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
        $this->merge([
            'language'    => $this->input('language', 'id'),
            'mcq_count'   => $this->input('mcq_count'),
            'essay_count' => $this->input('essay_count'),
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
            'type' => [
                'required',
                'string',
                'in:multiple_choice,essay,mixed',
            ],

            'difficulty' => [
                'required',
                'string',
                'in:easy,medium,hard',
            ],

            'max_score' => [
                'required',
                'integer',
                'min:1',
                'max:100',
            ],

            // Required when type is multiple_choice or mixed
            'mcq_count' => [
                'nullable',
                'required_if:type,multiple_choice',
                'required_if:type,mixed',
                'integer',
                'min:1',
                'max:50',
            ],

            // Required when type is essay or mixed
            'essay_count' => [
                'nullable',
                'required_if:type,essay',
                'required_if:type,mixed',
                'integer',
                'min:1',
                'max:20',
            ],

            'language' => [
                'nullable',
                'string',
                'in:id,en',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'mcq_count.required_if'   => 'Jumlah soal MCQ wajib diisi untuk tipe ini.',
            'essay_count.required_if' => 'Jumlah soal essay wajib diisi untuk tipe ini.',
        ];
    }
}
