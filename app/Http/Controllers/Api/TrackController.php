<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TrackIndexRequest;
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
    public function index(TrackIndexRequest $request)
    {
        $query = Track::with(['creator.roles', 'creator.user'])->withCount(['modules']);

        // Apply search filter
        $query->when($request->input('search'), function ($q, $search) {
            $q->where(function ($subQuery) use ($search) {
                $subQuery->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        });

        $query->orderBy('order');

        // Handle pagination
        $pagination = $request->input('pagination', true);

        if ($pagination === false || $pagination === 'false' || $pagination === 0) {
            $tracks = $query->get();

            if ($tracks->isEmpty()) {
                return $this->error('No tracks found', 404);
            }

            return $this->success(
                TrackResource::collection($tracks),
                'retrieved successfully'
            );
        }

        // Paginated response
        $perPage = $request->input('per_page', 15);
        $tracks = $query->paginate($perPage);

        if ($tracks->isEmpty()) {
            return $this->error('No tracks found', 404);
        }

        return $this->successWithPagination(
            TrackResource::collection($tracks->items()),
            'retrieved successfully',
            $tracks
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TrackStoreRequest $request)
    {
        $data = $request->validated();

        // Set created_by to authenticated user's profile_id
        if (auth()->check() && auth()->user()->profile) {
            $data['created_by'] = auth()->user()->profile->id;
        }

        $track = Track::create($data);

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
        $track->load(['creator.roles', 'creator.user']);
        $track->loadCount('modules');

        return $this->success(new TrackResource($track));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(TrackUpdateRequest $request, Track $track)
    {
        $track->update($request->validated());

        return $this->success(
            new TrackResource($track),
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
