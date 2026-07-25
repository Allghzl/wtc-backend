<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
    public function index()
    {
        $modules = Module::orderBy('order')->get();

        if ($modules->isEmpty()) {
            return $this->error('No modules found', 404);
        }

        return $this->success(
            ModuleResource::collection($modules),
            'retrieved successfully'
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
        $module = Module::create($request->validated());

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
