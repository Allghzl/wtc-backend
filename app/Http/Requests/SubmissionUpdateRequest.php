<?php

namespace App\Http\Requests;

use App\Traits\ApiValidationResponse;
use Illuminate\Foundation\Http\FormRequest;

class SubmissionUpdateRequest extends FormRequest
{
    use ApiValidationResponse;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $submission = $this->route('submission');
        $maxScore = $submission->challenge->max_score ?? 100;

        return [
            'manual_score' => [
                'nullable',
                'integer',
                'min:0',
                'max:' . $maxScore,
            ],

            'feedback' => [
                'nullable',
                'string',
            ],

            'status' => [
                'nullable',
                'string',
                'in:pending,graded,reviewed,rejected',
            ],
        ];
    }
}
