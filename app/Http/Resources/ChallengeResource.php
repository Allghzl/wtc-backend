<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChallengeResource extends JsonResource
{
    public function toArray($request): array
    {
        $user = $request->user();
        $isAdminOrTeacher = $user && ($user->hasRole('admin') || $user->hasRole('teacher'));

        return [
            'id'         => $this->id,
            'module_id'  => $this->module_id,
            'lesson_id'  => $this->lesson_id,
            'title'      => $this->title,
            'slug'       => $this->slug,
            'type'       => $this->type,
            'difficulty' => $this->difficulty,
            'order'      => $this->order,
            'content'    => $this->content,
            'settings'   => $isAdminOrTeacher
                ? $this->settings
                : $this->filterSensitiveSettings($this->settings),
            'metadata'   => $isAdminOrTeacher
                ? $this->metadata
                : $this->filterSensitiveMetadata($this->metadata),
            'max_score'  => $this->max_score,
            'points'     => $this->points,
            'allowed_attempts' => $this->allowed_attempts,
            'created_by' => $this->created_by,
            'creator' => new CreatorResource($this->whenLoaded('creator')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Filter sensitive data from settings for students.
     * Removes correct answer indicators (is_correct field).
     */
    protected function filterSensitiveSettings($settings)
    {
        if (!$settings || !is_array($settings)) {
            return $settings;
        }

        $filtered = $settings;

        // Remove is_correct from options (multiple_choice challenges)
        if (isset($filtered['options']) && is_array($filtered['options'])) {
            $filtered['options'] = array_map(function ($option) {
                if (is_array($option)) {
                    unset($option['is_correct']);
                }
                return $option;
            }, $filtered['options']);
        }

        return $filtered;
    }

    /**
     * Filter sensitive data from metadata for students.
     * Removes answer fields from questions.
     */
    protected function filterSensitiveMetadata($metadata)
    {
        if (!$metadata || !is_array($metadata)) {
            return $metadata;
        }

        $filtered = $metadata;

        // Remove answer from questions (quiz_group challenges)
        if (isset($filtered['questions']) && is_array($filtered['questions'])) {
            $filtered['questions'] = array_map(function ($question) {
                if (is_array($question)) {
                    unset($question['answer']);
                }
                return $question;
            }, $filtered['questions']);
        }

        return $filtered;
    }
}
