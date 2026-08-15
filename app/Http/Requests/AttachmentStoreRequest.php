<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachmentStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only admin and teacher can upload attachments
        return $this->user()->hasRole('admin') || $this->user()->hasRole('teacher');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:51200',
                'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip,png,jpg,jpeg,txt,csv,mp4,mov,avi,mkv',
            ],
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'type' => [
                'required',
                'string',
                'in:material,starter_file,example,template,reference,instruction',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'file.required' => 'File is required.',
            'file.file' => 'The uploaded file is invalid.',
            'file.max' => 'File size must not exceed 50MB.',
            'file.mimes' => 'File type is not supported.',
            'title.required' => 'Title is required.',
            'title.max' => 'Title must not exceed 255 characters.',
            'description.max' => 'Description must not exceed 1000 characters.',
            'type.required' => 'Attachment type is required.',
            'type.in' => 'Invalid attachment type. Must be one of: material, starter_file, example, template, reference, instruction.',
        ];
    }
}
