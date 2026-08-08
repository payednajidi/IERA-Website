<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BossQuestionnaireController;
use App\Http\Controllers\EraAssessmentController;
use App\Http\Controllers\EraChecklistController;
use App\Http\Controllers\EraEnvironmentalFactorController;
use App\Http\Controllers\EraForcefulExertionController;
use App\Http\Controllers\EraRepetitiveMotionController;
use App\Http\Controllers\EraSummaryController;
use App\Http\Controllers\EraVibrationController;

Route::get('/era-assessments', [EraAssessmentController::class, 'index']);
Route::get('/era-assessments/{assessmentId}', [EraAssessmentController::class, 'show']);
Route::put('/era-assessments/{assessmentId}', [EraAssessmentController::class, 'update']);
Route::post('/era-assessments', [EraAssessmentController::class, 'store']);
Route::post('/era-assessments/bulk-delete', [EraAssessmentController::class, 'destroyMany']);
Route::get('/era-checklist/{assessmentId}', [EraChecklistController::class, 'show']);
Route::post('/era-checklist', [EraChecklistController::class, 'store']);
Route::get('/era-forceful-exertion/{assessmentId}', [EraForcefulExertionController::class, 'show']);
Route::post('/era-forceful-exertion', [EraForcefulExertionController::class, 'store']);
Route::get('/era-repetitive-motion/{assessmentId}', [EraRepetitiveMotionController::class, 'show']);
Route::post('/era-repetitive-motion', [EraRepetitiveMotionController::class, 'store']);
Route::get('/era-vibration/{assessmentId}', [EraVibrationController::class, 'show']);
Route::post('/era-vibration', [EraVibrationController::class, 'store']);
Route::get('/era-environmental-factors/{assessmentId}', [EraEnvironmentalFactorController::class, 'show']);
Route::post('/era-environmental-factors', [EraEnvironmentalFactorController::class, 'store']);
Route::get('/era-summary-pain-parts/{assessmentId}', [EraSummaryController::class, 'showPainParts']);
Route::post('/era-summary-pain-parts', [EraSummaryController::class, 'savePainParts']);
Route::get('/boss-questionnaires/{id}', [BossQuestionnaireController::class, 'show']);
Route::post('/boss-questionnaires', [BossQuestionnaireController::class, 'store']);
Route::get('/boss-questionnaires/by-assessment/{assessmentId}', [BossQuestionnaireController::class, 'showByAssessment']);
