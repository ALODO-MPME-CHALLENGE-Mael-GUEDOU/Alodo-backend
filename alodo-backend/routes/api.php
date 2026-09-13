<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DiagnosticController;
use App\Http\Controllers\Api\QuestionsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'role:user'])->group(function () {

    Route::get('/display/questionnaire', [DiagnosticController::class, 'indexQuestionnaire']);
    Route::get('/diagnostics/{diagnostic}/result', [DiagnosticController::class, 'showResult'])->whereNumber('diagnostic');
    Route::post('/diagnostics/{diagnostic}/interpretation/retry', [DiagnosticController::class, 'retryInterpretation'])->whereNumber('diagnostic');
    Route::post('/store/answers', [DiagnosticController::class, 'storeAnswers']);

});

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {

    Route::post('/store/domains', [QuestionsController::class, 'storeDomain']);
    Route::post('/store/questions', [QuestionsController::class, 'storeQuestion']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});
