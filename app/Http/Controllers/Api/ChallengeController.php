<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChallengeIndexRequest;
use App\Http\Requests\ChallengeStoreRequest;
use App\Http\Requests\ChallengeUpdateRequest;
use App\Http\Resources\ChallengeResource;
use App\Models\Challenge;
use App\Models\Module;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ChallengeController extends Controller
{
    use ApiResponse;
    /**
     * Display a listing of the resource.
     */
    public function index(ChallengeIndexRequest $request)
    {
        $query = Challenge::query();

        // Filter by module_id if provided (challenges directly under a module, not under a lesson)
        $query->when($request->input('module_id'), function ($q, $moduleId) {
            $q->where('module_id', $moduleId)->whereNull('lesson_id');
        });

        // Filter by lesson_id if provided (challenges under a specific lesson)
        $query->when($request->input('lesson_id'), function ($q, $lessonId) {
            $q->where('lesson_id', $lessonId);
        });

        // Filter by track_id if provided (challenges in any module/lesson within that track)
        $query->when($request->input('track_id'), function ($q, $trackId) {
            $q->where(function ($subQuery) use ($trackId) {
                // Challenges directly under modules in this track
                $subQuery->whereHas('module', function ($moduleQuery) use ($trackId) {
                    $moduleQuery->where('track_id', $trackId);
                })
                // OR challenges under lessons whose modules are in this track
                ->orWhereHas('lesson.module', function ($moduleQuery) use ($trackId) {
                    $moduleQuery->where('track_id', $trackId);
                });
            });
        });

        $challenges = $query->orderBy('order')->get();

        if ($challenges->isEmpty()) {
            return $this->error('No challenges found', 404);
        }

        return $this->success(ChallengeResource::collection($challenges), 'Challenges data collected successfully');
    }

    /**
     * Get challenges by module
     */
    public function getByModule(Module $module)
    {
        $challenges = $module->challenges()->orderBy('order')->get();

        if ($challenges->isEmpty()) {
            return $this->error('No challenges found in this track', 404);
        }

        return $this->success(
            ChallengeResource::collection($challenges),
            'Challenges retrieved successfully'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ChallengeStoreRequest $request)
    {
        $challenge = Challenge::create($request->validated());

        if (!$challenge) {
            return $this->error('Failed to create challenge', 500);
        }

        return $this->success(new ChallengeResource($challenge), 'New challenge created successfully', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Challenge $challenge)
    {
        return $this->success(new ChallengeResource($challenge), 'Challenge detail retrieved successfully');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ChallengeUpdateRequest $request, Challenge $challenge)
    {
        $challenge->update($request->validated());

        return $this->success(new ChallengeResource($challenge), 'Challenge updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Challenge $challenge)
    {
        $challenge->delete();

        return $this->success(null, 'Challenge deleted successfully');
    }
}
