<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Tpadzik\Sheetflow\Http\Controllers\FailureController;
use Tpadzik\Sheetflow\Http\Controllers\RunController;
use Tpadzik\Sheetflow\Http\Controllers\TemplateController;
use Tpadzik\Sheetflow\Http\Controllers\TranslationController;
use Tpadzik\Sheetflow\Http\Controllers\WizardController;

Route::get('assets/wizard.js', [WizardController::class, 'script'])->name('assets.wizard');
Route::get('assets/wizard.css', [WizardController::class, 'stylesheet'])->name('assets.stylesheet');

Route::get('api/translations', [TranslationController::class, 'show'])->name('translations');
Route::post('api/{definition}/uploads', [RunController::class, 'store'])->name('uploads.store');
Route::get('api/{definition}/templates', [TemplateController::class, 'index'])->name('templates.index');
Route::get('api/runs/{run}', [RunController::class, 'show'])->name('runs.show');
Route::put('api/runs/{run}/mapping', [RunController::class, 'updateMapping'])->name('runs.mapping');
Route::put('api/runs/{run}/sheet', [RunController::class, 'updateSheet'])->name('runs.sheet');
Route::post('api/runs/{run}/validation', [RunController::class, 'validateRun'])->name('runs.validate');
Route::post('api/runs/{run}/import', [RunController::class, 'import'])->name('runs.import');
Route::post('api/runs/{run}/cancel', [RunController::class, 'cancel'])->name('runs.cancel');
Route::get('api/runs/{run}/failures', [FailureController::class, 'index'])->name('runs.failures');
