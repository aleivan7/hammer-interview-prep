<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransactionController extends Controller
{
    /**
     * List transactions for the review queue.
     */
    public function index(): AnonymousResourceCollection
    {
        /*
         * TODO (Alejandro): Query unreviewed transactions.
         *
         * Requirements:
         * - Only transactions where reviewed_at is null
         * - Ordered by transaction_date, oldest first
         * - Return them through TransactionResource::collection(...)
         *
         * Do not paste a finished solution under this comment.
         * The empty collection below is only a temporary placeholder.
         */
        
        $transactions = Transaction::whereNull('reviewed_at')->orderBy('transaction_date')->get();
        

        return TransactionResource::collection($transactions);
    }

    /**
     * Update a transaction's category and reviewed status.
     */
    public function update(
        UpdateTransactionRequest $request,
        Transaction $transaction,
    ): TransactionResource {
        /*
         * TODO (Alejandro): Apply the validated input to $transaction.
         *
         * Requirements:
         * - Set category from the request
         * - If reviewed is true, set reviewed_at to now()
         * - If reviewed is false, clear reviewed_at (set it to null)
         * - Save the model
         * - Return new TransactionResource($transaction)
         *
         * Tip: $request->validated() returns only the validated fields.
         *
         * Do not paste a finished solution under this comment.
         * Returning the unchanged model keeps the app runnable while you learn.
         */
        $transaction->update([
            'category' => $request->input('category'),
            'reviewed_at' => $request->input('reviewed') ? now() : null,
        ]);
        
        return new TransactionResource($transaction);
    }
}
