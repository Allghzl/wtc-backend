<?php

namespace App\Helpers;

use App\Models\Profile;
use Illuminate\Http\Request;

class RequestHelper
{
    /**
     * Get authenticated profile from request attributes
     *
     * @param Request $request
     * @return Profile|null
     */
    public static function getProfile(Request $request): ?Profile
    {
        return $request->attributes->get('profile');
    }

    /**
     * Get JWT payload from request attributes
     *
     * @param Request $request
     * @return object|null
     */
    public static function getPayload(Request $request): ?object
    {
        return $request->attributes->get('payload');
    }
}
