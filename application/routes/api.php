<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

//Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//    return $request->user();
//});


Route::prefix('/playground')
    ->controller(\App\Http\Controllers\PlaygroundController::class)
    ->group(function (): void {
        Route::get('/test1', 'test1');
        Route::get('/test2', 'test2');
        Route::get('/tm-content/{id}', 'tmContent');
    });

Route::get('/playground', [\App\Http\Controllers\PlaygroundController::class, 'index']);

Route::prefix('/projects')
    ->controller(\App\Http\Controllers\ProjectController::class)
    ->whereUuid('id')->group(function (): void {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

Route::prefix('/jobs')
    ->controller(\App\Http\Controllers\JobController::class)
    ->whereUuid('id')->group(function (): void {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
        Route::post('/{id}/download', 'download');
    });

Route::prefix('/segments')
    ->controller(\App\Http\Controllers\SegmentController::class)
    ->whereUuid('id')->group(function (): void {
        Route::get('/', 'index');
//        Route::post('/', 'store');
//        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
//        Route::delete('/{id}', 'destroy');
    });

Route::prefix('/analyses')
    ->controller(\App\Http\Controllers\AnalysisController::class)
    ->whereUuid('id')->group(function (): void {
        Route::get('/', 'index');
        Route::post('/', 'store');
//        Route::get('/{id}', 'show');
//        Route::put('/{id}', 'update');
//        Route::delete('/{id}', 'destroy');
    });

Route::prefix('/translation-memories')
    ->controller(\App\Http\Controllers\TranslationMemoryController::class)
    ->whereUuid('id')->group(function (): void {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::post('/import', 'import');
        Route::post('/export', 'export');
        Route::put('/{id}', 'update');
//        Route::delete('/{id}', 'destroy');
    });

Route::post('/download', [\App\Http\Controllers\DownloadController::class, 'download']);

Route::controller(\App\Http\Controllers\SuggestionController::class)
    ->whereUuid('job_id')
    ->group(function (): void {
        Route::get('/suggestions', 'index');
        Route::get('/jobs/{job_id}/suggestions', 'indexJob');
    });

Route::prefix('/nectm-replacement')
    ->controller(\App\Http\Controllers\NectmReplacementController::class)
    ->group(function (): void {
        Route::get('/tm', 'index');
        Route::post('/tm', 'store');
    });