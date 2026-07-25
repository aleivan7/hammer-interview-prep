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
        $data = $request->validated();

        $transaction->update([
            'category' => $data['category'],
            'reviewed_at' => $data['reviewed'] ? now() : null,
        ]);

        return new TransactionResource($transaction);
    }
}
