<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudyClassRequest;
use App\Http\Requests\StudyClassIndexRequest;
use App\Http\Requests\UpdateStudyClassRequest;
use App\Models\StudyClass;
use App\Models\Track;
use App\Traits\ApiResponse;

class StudyClassController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of the resource.
     */
    public function index(StudyClassIndexRequest $request)
    {
        $query = StudyClass::query();

        $query->when($request->input('search'), function ($q, $search) {
            $q->where('name', 'like', "%{$search}%");
        });

        // Student-facing: only active classes
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        // Include tracks if requested
        if ($request->boolean('with_tracks')) {
            $query->with(['tracks' => fn ($q) => $q->where('is_active', true)->orderBy('order')]);
        }

        $pagination = $request->input('pagination', true);

        if ($pagination === false || $pagination === 'false' || $pagination === 0) {
            return $this->success($query->get());
        }

        $perPage = $request->input('per_page', 15);
        $studyClasses = $query->paginate($perPage);

        return $this->successWithPagination(
            $studyClasses->items(),
            'Success',
            $studyClasses
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStudyClassRequest $request)
    {
        $studyClass = StudyClass::create($request->validated());

        return $this->success($studyClass, 'Study class created successfully.', 201);
    }

    /**
     * Display the specified resource with its tracks.
     */
    public function show(string $id)
    {
        $studyClass = StudyClass::with([
            'tracks' => fn ($q) => $q->orderBy('order'),
        ])->findOrFail($id);

        return $this->success($studyClass);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStudyClassRequest $request, string $id)
    {
        $studyClass = StudyClass::findOrFail($id);
        $studyClass->update($request->validated());

        return $this->success($studyClass, 'Study class updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $studyClass = StudyClass::findOrFail($id);
        $studyClass->delete();

        return $this->success(null, 'Study class deleted successfully.');
    }

    /**
     * Toggle is_active for a study class.
     */
    public function toggle(string $id)
    {
        $studyClass = StudyClass::findOrFail($id);
        $studyClass->update(['is_active' => !$studyClass->is_active]);

        return $this->success(
            $studyClass,
            "Study class " . ($studyClass->is_active ? 'activated' : 'deactivated') . " successfully."
        );
    }

    /**
     * Assign a track to a study class.
     */
    public function assignTrack(string $id, string $trackId)
    {
        $studyClass = StudyClass::findOrFail($id);
        $track = Track::findOrFail($trackId);

        if ($studyClass->tracks()->where('track_id', $track->id)->exists()) {
            return $this->error('Track is already assigned to this study class.', 409);
        }

        $studyClass->tracks()->attach($track->id);

        $studyClass->load(['tracks' => fn ($q) => $q->orderBy('order')]);

        return $this->success($studyClass, 'Track assigned successfully.');
    }

    /**
     * Remove a track from a study class.
     */
    public function removeTrack(string $id, string $trackId)
    {
        $studyClass = StudyClass::findOrFail($id);
        $track = Track::findOrFail($trackId);

        $studyClass->tracks()->detach($track->id);

        $studyClass->load(['tracks' => fn ($q) => $q->orderBy('order')]);

        return $this->success($studyClass, 'Track removed successfully.');
    }

    /**
     * Get the study class for the authenticated user's profile.
     */
    public function myClass()
    {
        $profile = auth()->user()->profile;

        if (!$profile || !$profile->study_class_id) {
            return $this->error('You are not enrolled in any study class.', 404);
        }

        $studyClass = StudyClass::with([
            'tracks' => fn ($q) => $q->where('is_active', true)->orderBy('order'),
        ])->findOrFail($profile->study_class_id);

        return $this->success($studyClass);
    }

    /**
     * Assign the authenticated user to a study class.
     */
    public function joinClass(string $id)
    {
        $studyClass = StudyClass::where('is_active', true)->findOrFail($id);
        $profile = auth()->user()->profile;

        if (!$profile) {
            return $this->error('Profile not found.', 404);
        }

        $profile->update(['study_class_id' => $studyClass->id]);

        $studyClass->load(['tracks' => fn ($q) => $q->where('is_active', true)->orderBy('order')]);

        return $this->success($studyClass, "Successfully joined {$studyClass->name}.");
    }
}
