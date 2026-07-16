<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LessonStoreRequest;
use App\Http\Requests\LessonUpdateRequest;
use App\Models\Lesson;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    use ApiResponse;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $lessons = Lesson::orderBy('order')->get();

        if ($lessons->isEmpty()) {
            return $this->error('No lessons found', 404);
        }

        return $this->success(
            $lessons,
            'retrieved successfully'
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
