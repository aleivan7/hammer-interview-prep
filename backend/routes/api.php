<?php

use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

/*
 * API routes are automatically prefixed with /api
 * because they are registered with withRouting(api: ...) in bootstrap/app.php.
 *
 * So this file defines /transactions, which becomes GET/PATCH /api/transactions.
 */
Route::get('/transactions', [TransactionController::class, 'index']);
Route::patch('/transactions/{transaction}', [TransactionController::class, 'update']);
