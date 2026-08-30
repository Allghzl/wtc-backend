<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CertificateTemplate;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateTemplateController extends Controller
{
    use ApiResponse;

    /**
     * GET /certificate-template
     * Return the currently active template (teacher_or_admin).
     */
    public function show(): JsonResponse
    {
        $template = CertificateTemplate::where('is_active', true)->first();

        if (!$template) {
            return $this->error('No active certificate template found.', 404);
        }

        return $this->success($template, 'Certificate template retrieved successfully.');
    }

    /**
     * POST /admin/certificate-template
     * Upsert: deactivate all existing templates, then create a new active one.
     * Admin only.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'html_template'  => ['required', 'string'],
            'css_styles'     => ['nullable', 'string'],
            'background_url' => ['nullable', 'string', 'max:255'],
            'logo_url'       => ['nullable', 'string', 'max:255'],
            'signature_url'  => ['nullable', 'string', 'max:255'],
        ]);

        // Deactivate all existing templates
        CertificateTemplate::where('is_active', true)->update(['is_active' => false]);

        $profile = $request->user()->profile;

        $template = CertificateTemplate::create(array_merge($validated, [
            'is_active'  => true,
            'created_by' => $profile?->id,
        ]));

        return $this->success($template, 'Certificate template saved successfully.', 201);
    }
}
