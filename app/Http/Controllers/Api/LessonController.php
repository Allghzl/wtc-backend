<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LessonIndexRequest;
use App\Http\Requests\LessonStoreRequest;
use App\Http\Requests\LessonUpdateRequest;
use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use App\Models\Module;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    use ApiResponse;
    /**
     * Display a listing of the resource.
     */
    public function index(LessonIndexRequest $request)
    {
        $query = Lesson::query();

        // Filter by module_id if provided
        $query->when($request->input('module_id'), function ($q, $moduleId) {
            $q->where('module_id', $moduleId);
        });

        // Filter by track_id if provided
        $query->when($request->input('track_id'), function ($q, $trackId) {
            $q->whereHas('module', function ($moduleQuery) use ($trackId) {
                $moduleQuery->where('track_id', $trackId);
            });
        });

        // Apply search filter
        $query->when($request->input('search'), function ($q, $search) {
            $q->where(function ($subQuery) use ($search) {
                $subQuery->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        });

        $query->orderBy('order');

        // Handle pagination
        $pagination = $request->input('pagination', true);

        if ($pagination === false || $pagination === 'false' || $pagination === 0) {
            $lessons = $query->get();

            if ($lessons->isEmpty()) {
                return $this->error('No lessons found', 404);
            }

            return $this->success(
                $lessons,
                'retrieved successfully'
            );
        }

        // Paginated response
        $perPage = $request->input('per_page', 15);
        $lessons = $query->paginate($perPage);

        if ($lessons->isEmpty()) {
            return $this->error('No lessons found', 404);
        }

        return $this->successWithPagination(
            $lessons->items(),
            'retrieved successfully',
            $lessons
        );
    }

    /**
     * Get lessons by module
     */
    public function getByModule(Module $module)
    {
        $lessons = $module->lessons()->orderBy('order')->get();

        if ($lessons->isEmpty()) {
            return $this->error('No lessons found in this track', 404);
        }

        return $this->success(
            $lessons,
            'Lessons retrieved successfully'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(LessonStoreRequest $request)
    {
        $lesson = Lesson::create($request->validated());

        return $this->success(
            $lesson,
            'Lesson created successfully',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Lesson $lesson)
    {
        return $this->success(
            $lesson,
            'Lesson retrieved successfully'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(LessonUpdateRequest $request, Lesson $lesson)
    {
        $lesson->update($request->validated());

        return $this->success(
            $lesson,
            'Lesson updated successfully'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Lesson $lesson)
    {
        $lesson->delete();

        return $this->success(
            null,
            'Lesson deleted successfully'
        );
    }
}
