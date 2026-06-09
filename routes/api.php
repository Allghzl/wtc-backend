<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChallengeController;
use App\Http\Controllers\Api\SubmissionController;
use App\Http\Controllers\Api\TrackController;
use App\Http\Controllers\Api\LessonController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::apiResource('tracks', TrackController::class);
    Route::apiResource('challenges', ChallengeController::class);
    Route::post('/challenges/{challenge}/submit', [SubmissionController::class, 'store']);
    Route::apiResource('lessons', LessonController::class);
});
