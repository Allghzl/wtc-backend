<?php

use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChallengeAttachmentController;
use App\Http\Controllers\Api\ChallengeController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\LessonAttachmentController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\LessonCompletionController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\ModuleController;

use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\StudyClassController;
use App\Http\Controllers\Api\SubmissionController;
use App\Http\Controllers\Api\TrackController;
use App\Http\Controllers\Api\UserController;
use App\Services\PinatJwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/auth/sso', [AuthController::class, 'sso']);

Route::get(
    '/user',
    function (Request $request) {
        return $request->user();
    }
)->middleware('auth:sanctum');
Route::post(
    '/logout',
    [AuthController::class, 'logout']
)->middleware('auth:sanctum');

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

    // Lesson Completion
    Route::post('/lessons/{lesson}/complete',           [LessonCompletionController::class, 'complete']);

    // Lesson Attachments
    Route::get('/lessons/{lesson}/attachments',         [LessonAttachmentController::class, 'index']);
    Route::post('/lessons/{lesson}/attachments',        [LessonAttachmentController::class, 'store']);
    Route::delete('/lessons/{lesson}/attachments/{attachment}', [LessonAttachmentController::class, 'destroy']);

    // Challenge Attachments
    Route::get('/challenges/{challenge}/attachments',   [ChallengeAttachmentController::class, 'index']);
    Route::post('/challenges/{challenge}/attachments',  [ChallengeAttachmentController::class, 'store']);
    Route::delete('/challenges/{challenge}/attachments/{attachment}', [ChallengeAttachmentController::class, 'destroy']);

    // Generic Attachment File Download
    Route::get('/attachments/{attachment}/file',        [AttachmentController::class, 'file']);
    Route::get('/attachments/{attachment}/download',    [AttachmentController::class, 'download']);

    // Track Enrollment
    Route::post('/tracks/{track}/enroll',               [EnrollmentController::class, 'enroll']);
    Route::delete('/tracks/{track}/enroll',             [EnrollmentController::class, 'unenroll']);
    Route::get('/tracks/{track}/enrollment',            [EnrollmentController::class, 'getEnrollment']);

    // My Tracks & Progress
    Route::get('/my/tracks',                            [EnrollmentController::class, 'myTracks']);
    Route::get('/my/progress',                          [EnrollmentController::class, 'myProgress']);
    Route::get('/my/tracks/{track}/progress',           [EnrollmentController::class, 'trackProgress']);
    Route::get('/my/tracks/{track}/overview',           [EnrollmentController::class, 'trackOverview']);
    Route::get('/my/dashboard',                         [EnrollmentController::class, 'dashboard']);

    Route::apiResource('tracks', TrackController::class);
    Route::apiResource('modules', ModuleController::class);
    Route::apiResource('lessons', LessonController::class);
    Route::apiResource('challenges', ChallengeController::class);
    Route::apiResource('study-classes', StudyClassController::class);

    Route::get('/me', [AuthController::class, 'me']);

    // Leaderboard
    Route::get('/leaderboard', [LeaderboardController::class, 'index']);
    Route::get('/profiles/{profile}/points-history', [LeaderboardController::class, 'pointsHistory']);

    // Profile Management
    Route::get('/profiles', [ProfileController::class, 'index']);
    Route::get('/profiles/{profile}', [ProfileController::class, 'show']);
    Route::put('/profiles/{profile}', [ProfileController::class, 'update']);

    // Avatar Management
    Route::post('/profiles/{profile}/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::delete('/profiles/{profile}/avatar', [ProfileController::class, 'deleteAvatar']);

    // Role Management
    Route::get('/profiles/{profile}/roles', [ProfileController::class, 'getRoles']);
    Route::post('/profiles/{profile}/roles', [ProfileController::class, 'assignRole']);
    Route::delete('/profiles/{profile}/roles/{role}', [ProfileController::class, 'removeRole']);

    // User Management
    Route::get('/users/stats', [UserController::class, 'stats']);
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show']);

    // Role CRUD
    Route::get('/roles', [RoleController::class, 'index']);
    Route::get('/roles/{role}', [RoleController::class, 'show']);

    /*
    |--------------------------------------------------------------------------
    | Audit Log Endpoints
    |--------------------------------------------------------------------------
    | Get audit logs for specific resources and general audit logs with filters.
    | Accessible by authenticated users.
    */
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
    Route::get('/tracks/{track}/audit-log', [AuditLogController::class, 'trackAuditLog']);
    Route::get('/modules/{module}/audit-log', [AuditLogController::class, 'moduleAuditLog']);
    Route::get('/lessons/{lesson}/audit-log', [AuditLogController::class, 'lessonAuditLog']);
    Route::get('/challenges/{challenge}/audit-log', [AuditLogController::class, 'challengeAuditLog']);

    // Admin-only role operations
    Route::middleware('admin')->group(function () {
        Route::post('/roles', [RoleController::class, 'store']);
        Route::put('/roles/{role}', [RoleController::class, 'update']);
        Route::delete('/roles/{role}', [RoleController::class, 'destroy']);

        /*
        |--------------------------------------------------------------------------
        | Admin-Only Restore Endpoints
        |--------------------------------------------------------------------------
        | Restore soft-deleted content. Only accessible by users with admin role.
        | Teachers cannot restore to encourage careful deletion.
        */
        Route::post('/admin/tracks/{id}/restore', [AuditLogController::class, 'restoreTrack']);
        Route::post('/admin/modules/{id}/restore', [AuditLogController::class, 'restoreModule']);
        Route::post('/admin/lessons/{id}/restore', [AuditLogController::class, 'restoreLesson']);
        Route::post('/admin/challenges/{id}/restore', [AuditLogController::class, 'restoreChallenge']);
    });
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
