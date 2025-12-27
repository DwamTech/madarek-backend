<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\SectionController;
use App\Http\Middleware\EnsureVisitorCookie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public section routes
Route::get('/sections', [SectionController::class, 'index']);
Route::get('/sections/{id}', [SectionController::class, 'show']);

// Public issue routes
Route::get('/issues', [IssueController::class, 'index']);
Route::get('/issues/{id}', [IssueController::class, 'show']);

// Public article routes
Route::get('/articles', [ArticleController::class, 'index']);
Route::get('/articles/{id}', [ArticleController::class, 'show'])
    ->middleware(EnsureVisitorCookie::class);

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Protected issue routes
    Route::post('/issues', [IssueController::class, 'store']);
    Route::post('/issues/{issue}/publish', [IssueController::class, 'publish']);
    Route::put('/issues/{issue}', [IssueController::class, 'update']);
    Route::delete('/issues/{issue}', [IssueController::class, 'destroy']);

    // Protected article routes (Create, Update, Delete)
    // Create article with section and issue in URL
    Route::post('/sections/{section}/issues/{issue}/articles', [ArticleController::class, 'store']);

    // Legacy store route (optional, can keep or remove based on preference, removing to force new structure)
    // Route::post('/articles', [ArticleController::class, 'store']);

    Route::put('/articles/{article}', [ArticleController::class, 'update']);
    Route::delete('/articles/{article}', [ArticleController::class, 'destroy']);

    // Backup Routes (Admin only)
    Route::middleware(['admin'])->group(function () {
        Route::get('/backups', [BackupController::class, 'index']);
        Route::get('/backups/history', [BackupController::class, 'history']);
        Route::post('/backups/upload', [BackupController::class, 'upload']);
        Route::get('/backups/download', [BackupController::class, 'download'])->name('backup.download');
        Route::post('/backups/create', [BackupController::class, 'create']);
        Route::post('/backups/restore', [BackupController::class, 'restore']);
    });
});
