<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChallengeController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\ModuleController;
use App\Http\Controllers\Api\StudyClassController;
use App\Http\Controllers\Api\SubmissionController;
use App\Http\Controllers\Api\TrackController;
use App\Services\PinatJwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::get('/tracks/{track}/modules', [ModuleController::class, 'getByTrack']);
    Route::get('/modules/{module}/lessons', [LessonController::class, 'getByModule']);
    Route::get('/modules/{module}/challenges', [ChallengeController::class, 'getByModule']);

    Route::get('/challenges/{challenge}/submissions',   [SubmissionController::class, 'index']);
    Route::post('/challenges/{challenge}/submit',       [SubmissionController::class, 'store']);
    Route::get('/challenges/{challenge}/my-submissions', [SubmissionController::class, 'mySubmissions']);
    Route::get('/submissions/{submission}',             [SubmissionController::class, 'show']);
    Route::patch('/submissions/{submission}',           [SubmissionController::class, 'update']);
    Route::get('/submissions/{submission}/file',        [SubmissionController::class, 'file']);

    Route::apiResource('tracks', TrackController::class);
    Route::apiResource('modules', ModuleController::class);
    Route::apiResource('lessons', LessonController::class);
    Route::apiResource('challenges', ChallengeController::class);
    Route::apiResource('study-classes', StudyClassController::class);

    Route::get('/me', [AuthController::class, 'me']);
});



Route::get('/debug/token', function (
    Request $request,
    PinatJwtService $jwt
) {
    $token = $jwt->getBearerToken($request);

    return [
        'kid' => $jwt->getKid($token),
        'header' => $jwt->getHeader($token),
    ];
});
