<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\Module;
use App\Models\Profile;
use App\Models\Track;
use Illuminate\Support\Collection;

class LearningStateService
{
    public function __construct(
        protected LessonCompletionService $lessonCompletionService
    ) {}

    /**
     * Get the current lesson (first incomplete lesson) for a track.
     */
    public function getCurrentLesson(Track $track, Profile $profile): ?Lesson
    {
        $modules = $track->modules()
            ->with(['lessons' => function ($query) {
                $query->orderBy('order');
            }])
            ->orderBy('order')
            ->get();

        foreach ($modules as $module) {
            $lessons = $module->lessons; // Use eager-loaded collection

            foreach ($lessons as $lesson) {
                if (!$this->lessonCompletionService->isLessonCompleted($lesson, $profile)) {
                    return $lesson;
                }
            }
        }

        return null; // All lessons completed
    }

    /**
     * Get the next lesson after the current one.
     */
    public function getNextLesson(Track $track, Profile $profile): ?Lesson
    {
        $currentLesson = $this->getCurrentLesson($track, $profile);

        if (!$currentLesson) {
            return null; // No incomplete lessons
        }

        // Get all lessons in order
        $allLessons = $this->getAllLessonsInOrder($track);

        // Find current lesson index
        $currentIndex = $allLessons->search(function ($lesson) use ($currentLesson) {
            return $lesson->id === $currentLesson->id;
        });

        // Return next lesson if exists
        if ($currentIndex !== false && $currentIndex < $allLessons->count() - 1) {
            return $allLessons[$currentIndex + 1];
        }

        return null;
    }

    /**
     * Check if a lesson is unlocked for the profile.
     * A lesson is unlocked if all previous lessons are completed.
     */
    public function isLessonUnlocked(Lesson $lesson, Profile $profile): bool
    {
        $track = $lesson->module->track;
        $allLessons = $this->getAllLessonsInOrder($track);

        // Find the lesson's position
        $lessonIndex = $allLessons->search(function ($l) use ($lesson) {
            return $l->id === $lesson->id;
        });

        if ($lessonIndex === false) {
            return false;
        }

        // First lesson is always unlocked
        if ($lessonIndex === 0) {
            return true;
        }

        // Check if all previous lessons are completed
        $previousLessons = $allLessons->slice(0, $lessonIndex);

        foreach ($previousLessons as $previousLesson) {
            if (!$this->lessonCompletionService->isLessonCompleted($previousLesson, $profile)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the state of a lesson: 'completed', 'current', or 'locked'
     */
    public function getLessonState(Lesson $lesson, Profile $profile): string
    {
        // Check if completed
        if ($this->lessonCompletionService->isLessonCompleted($lesson, $profile)) {
            return 'completed';
        }

        // Check if unlocked
        if ($this->isLessonUnlocked($lesson, $profile)) {
            return 'current';
        }

        return 'locked';
    }

    /**
     * Get continue learning information for a profile.
     * Returns the track, module, and lesson where they should continue.
     */
    public function getContinueLearning(Profile $profile): ?array
    {
        // Get active enrollments
        $enrollments = $profile->trackEnrollments()
            ->where('status', 'active')
            ->with('track')
            ->orderBy('enrolled_at', 'desc')
            ->get();

        if ($enrollments->isEmpty()) {
            return null;
        }

        // Find the first track with incomplete lessons
        foreach ($enrollments as $enrollment) {
            $track = $enrollment->track;
            $currentLesson = $this->getCurrentLesson($track, $profile);

            if ($currentLesson) {
                return [
                    'track' => $track,
                    'module' => $currentLesson->module,
                    'lesson' => $currentLesson,
                    'enrollment' => $enrollment,
                ];
            }
        }

        // All enrolled tracks are completed
        return null;
    }

    /**
     * Get all lessons for a track in the correct learning order.
     */
    protected function getAllLessonsInOrder(Track $track): Collection
    {
        $modules = $track->modules()
            ->with(['lessons' => function ($query) {
                $query->orderBy('order');
            }])
            ->orderBy('order')
            ->get();

        $allLessons = collect();

        foreach ($modules as $module) {
            $lessons = $module->lessons; // Use eager-loaded collection
            $allLessons = $allLessons->concat($lessons);
        }

        return $allLessons;
    }

    /**
     * Get learning state for all lessons in a track.
     * Returns array keyed by lesson_id with state values.
     */
    public function getTrackLessonStates(Track $track, Profile $profile): array
    {
        $allLessons = $this->getAllLessonsInOrder($track);
        $lessonIds = $allLessons->pluck('id')->toArray();

        // Batch check completions
        $completions = $this->lessonCompletionService->areLessonsCompleted($lessonIds, $profile);

        $states = [];
        $foundIncomplete = false;

        foreach ($allLessons as $lesson) {
            $isCompleted = $completions[$lesson->id] ?? false;

            if ($isCompleted) {
                $states[$lesson->id] = 'completed';
            } elseif (!$foundIncomplete) {
                $states[$lesson->id] = 'current';
                $foundIncomplete = true;
            } else {
                $states[$lesson->id] = 'locked';
            }
        }

        return $states;
    }
}
