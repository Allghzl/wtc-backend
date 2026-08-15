<?php

namespace App\Http\Requests;

use App\Traits\ApiValidationResponse;
use Illuminate\Foundation\Http\FormRequest;

class TrackEnrollRequest extends FormRequest
{
    use ApiValidationResponse;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Any authenticated user can enroll
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // No request body needed for enrollment
        return [];
    }
}
