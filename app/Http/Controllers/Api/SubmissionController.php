<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmissionStoreRequest;
use App\Actions\ProcessSubmissionAction;
use App\Http\Resources\SubmissionResource;
use App\Models\Challenge;
use App\Models\Submission;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    use ApiResponse;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SubmissionStoreRequest $request, ProcessSubmissionAction $action, Challenge $challenge)
    {
        $submission = $action->execute(
            $request->user(),
            $challenge,
            $request->validated(),
            $request->file('file')
        );

        return $this->success(
            new SubmissionResource($submission),
            'submitted successfully.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Submission $submission)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Submission $submission)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Submission $submission)
    {
        //
    }
}
