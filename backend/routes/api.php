<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CategorizationRuleController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoUserController;
use App\Http\Controllers\MerchantController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SmartReviewController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/demo-users', [DemoUserController::class, 'index']);

Route::middleware('demo.user')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile/reset', [ProfileController::class, 'reset']);

    Route::get('/dashboard', [DashboardController::class, 'show']);
    Route::get('/accounts', [AccountController::class, 'index']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::patch('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
    Route::get('/merchants', [MerchantController::class, 'index']);

    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::patch('/transactions/{transaction}', [TransactionController::class, 'update']);
    Route::post('/transactions/{transaction}/undo', [TransactionController::class, 'undo']);
    Route::get('/transactions/{transaction}/suggestion', [TransactionController::class, 'suggestion']);

    Route::post('/smart-review', [SmartReviewController::class, 'store']);

    Route::get('/rules', [CategorizationRuleController::class, 'index']);
    Route::post('/rules', [CategorizationRuleController::class, 'store']);
    Route::patch('/rules/{categorization_rule}', [CategorizationRuleController::class, 'update']);
    Route::delete('/rules/{categorization_rule}', [CategorizationRuleController::class, 'destroy']);
});
