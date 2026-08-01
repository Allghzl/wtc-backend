<?php

namespace App\Http\Controllers;

use App\Helpers\RequestHelper;
use Illuminate\Http\Request;

class ExampleController extends Controller
{
    /**
     * Example of accessing authenticated profile
     */
    public function index(Request $request)
    {
        // Get profile from request attributes
        $profile = RequestHelper::getProfile($request);

        // Alternative: Direct access
        // $profile = $request->attributes->get('profile');

        return response()->json([
            'message' => 'Authenticated successfully',
            'profile' => $profile,
            'puid' => $profile->puid,
            'nickname' => $profile->nickname,
            'points' => $profile->points,
        ]);
    }

    /**
     * Example of accessing JWT payload
     */
    public function getPayload(Request $request)
    {
        $payload = RequestHelper::getPayload($request);

        return response()->json([
            'payload' => $payload,
        ]);
    }
}
