<?php

namespace App\Http\Controllers;

use App\Enums\Bucket;
use App\Enums\ReviewSource;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\TransactionReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionReviewService $reviewService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Transaction::query()->with('account')->orderByDesc('transaction_date')->orderByDesc('id');

        if ($request->boolean('unreviewed_only') || $request->query('queue') === 'review') {
            $query->unreviewed()->reorder()->orderBy('transaction_date')->orderBy('id');
        }

        if ($request->filled('reviewed')) {
            $request->boolean('reviewed') ? $query->reviewed() : $query->unreviewed();
        }

        if ($request->filled('bucket')) {
            $query->where('bucket', $request->string('bucket'));
        }

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->integer('account_id'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where('merchant', 'like', "%{$search}%");
        }

        if ($request->boolean('paginate', true) && ! $request->boolean('unreviewed_only') && $request->query('queue') !== 'review') {
            return TransactionResource::collection($query->paginate(25));
        }

        return TransactionResource::collection($query->get());
    }

    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $reviewed = (bool) ($data['reviewed'] ?? false);

        $transaction = Transaction::query()->create([
            'account_id' => $data['account_id'] ?? null,
            'merchant' => $data['merchant'],
            'amount_cents' => $data['amount_cents'],
            'kind' => $data['kind'],
            'bucket' => $data['bucket'] ?? null,
            'subcategory' => $data['subcategory'] ?? null,
            'transaction_date' => $data['transaction_date'],
            'notes' => $data['notes'] ?? null,
            'reviewed_at' => $reviewed ? now() : null,
            'review_source' => $reviewed ? ReviewSource::Manual : null,
        ]);

        return (new TransactionResource($transaction->load('account')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateTransactionRequest $request,
        Transaction $transaction,
    ): TransactionResource {
        $data = $request->validated();
        $attributes = collect($data)->except(['reviewed', 'category'])->all();

        if (array_key_exists('reviewed', $data) && $data['reviewed'] === true) {
            $bucketValue = $data['bucket'] ?? $transaction->bucket?->value;

            if ($bucketValue === null) {
                throw new UnprocessableEntityHttpException('A bucket is required to mark a transaction reviewed.');
            }

            if ($transaction->isReviewed()) {
                $transaction->fill($attributes);
                $transaction->save();
            } else {
                $transaction->fill(collect($attributes)->except(['bucket', 'subcategory'])->all());
                $transaction->save();

                $transaction = $this->reviewService->review(
                    transaction: $transaction,
                    bucket: $bucketValue instanceof Bucket ? $bucketValue : Bucket::from($bucketValue),
                    subcategory: $data['subcategory'] ?? $transaction->subcategory,
                    source: ReviewSource::Manual,
                    confidence: 100,
                    explanation: 'Manually reviewed.',
                );
            }
        } elseif (array_key_exists('reviewed', $data) && $data['reviewed'] === false) {
            if ($transaction->isReviewed()) {
                $transaction = $this->reviewService->undo($transaction);
            }

            $transaction->fill($attributes);
            $transaction->save();
        } else {
            $transaction->fill($attributes);
            $transaction->save();
        }

        return new TransactionResource($transaction->load('account'));
    }

    public function undo(Transaction $transaction): TransactionResource
    {
        return new TransactionResource($this->reviewService->undo($transaction)->load('account'));
    }

    public function suggestion(Transaction $transaction): JsonResponse
    {
        $result = $this->reviewService->suggest($transaction);

        return response()->json([
            'data' => [
                'bucket' => $result->bucket?->value,
                'subcategory' => $result->subcategory,
                'confidence' => $result->confidence,
                'source' => $result->source->value,
                'explanation' => $result->explanation,
                'auto_review' => $result->autoReview,
            ],
        ]);
    }
}
