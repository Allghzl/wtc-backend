<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TrackStoreRequest;
use App\Http\Requests\TrackUpdateRequest;
use App\Http\Resources\TrackResource;
use App\Models\Track;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class TrackController extends Controller
{
    use ApiResponse;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tracks = Track::withCount(['modules'])->orderBy('order')->get();

        if ($tracks->isEmpty()) {
            return $this->error('No tracks found', 404);
        }

        return $this->success(
            TrackResource::collection($tracks),
            'retrieved successfully'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TrackStoreRequest $request)
    {
        $track = Track::create($request->validated());

        if (!$track) {
            return $this->error('Failed to create track', 500);
        }

        return $this->success(
            new TrackResource($track),
            'Track created successfully',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Track $track)
    {
        return $this->success(new TrackResource($track));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(TrackUpdateRequest $request, Track $track)
    {
        $track->update($request->validated());

        return $this->success(
            new TrackResource($track->fresh()),
            'Track updated successfully'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Track $track)
    {
        $track->delete();

        return $this->success(null, 'Track deleted successfully');
    }
}
