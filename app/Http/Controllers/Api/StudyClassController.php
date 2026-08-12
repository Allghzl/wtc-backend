<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudyClassRequest;
use App\Http\Requests\StudyClassIndexRequest;
use App\Http\Requests\UpdateStudyClassRequest;
use App\Models\StudyClass;
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

        // Apply search filter
        $query->when($request->input('search'), function ($q, $search) {
            $q->where('name', 'like', "%{$search}%");
        });

        // Handle pagination
        $pagination = $request->input('pagination', true);

        if ($pagination === false || $pagination === 'false' || $pagination === 0) {
            $studyClasses = $query->get();

            return $this->success($studyClasses);
        }

        // Paginated response
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
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $studyClass = StudyClass::findOrFail($id);

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
}
