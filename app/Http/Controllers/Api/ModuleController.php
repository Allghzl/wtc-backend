<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ModuleIndexRequest;
use App\Http\Requests\ModuleStoreRequest;
use App\Http\Requests\ModuleUpdateRequest;
use App\Http\Resources\ModuleResource;
use App\Models\Module;
use App\Models\Track;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    use ApiResponse;
    /**
     * Display a listing of the resource.
     */
    public function index(ModuleIndexRequest $request)
    {
        $query = Module::query();

        // Apply existing track_id filter
        $query->when($request->input('track_id'), function ($q, $trackId) {
            $q->where('track_id', $trackId);
        });

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
            $modules = $query->get();

            if ($modules->isEmpty()) {
                return $this->error('No modules found', 404);
            }

            return $this->success(
                ModuleResource::collection($modules),
                'retrieved successfully'
            );
        }

        // Paginated response
        $perPage = $request->input('per_page', 15);
        $modules = $query->paginate($perPage);

        if ($modules->isEmpty()) {
            return $this->error('No modules found', 404);
        }

        return $this->successWithPagination(
            ModuleResource::collection($modules->items()),
            'retrieved successfully',
            $modules
        );
    }

    /**
     * Get modules by track
     */
    public function getByTrack(Track $track)
    {
        $modules = $track->modules()->orderBy('order')->get();

        if ($modules->isEmpty()) {
            return $this->error('No modules found in this track', 404);
        }

        return $this->success(
            ModuleResource::collection($modules),
            'Modules retrieved successfully'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ModuleStoreRequest $request)
    {
        $data = $request->validated();

        // Set created_by to authenticated user's profile_id
        if (auth()->check() && auth()->user()->profile) {
            $data['created_by'] = auth()->user()->profile->id;
        }

        $module = Module::create($data);

        if (!$module) {
            return $this->error('Failed to create module', 500);
        }

        return $this->success(
            new ModuleResource($module),
            'Module created successfully',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Module $module)
    {
        return $this->success(new ModuleResource($module));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ModuleUpdateRequest $request, Module $module)
    {
        $module->update($request->validated());

        return $this->success(new ModuleResource($module), 'Module updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Module $module)
    {
        $module->delete();

        return $this->success(null, 'Module deleted successfully');
    }
}
