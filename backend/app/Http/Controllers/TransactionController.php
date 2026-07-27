<?php

namespace App\Http\Controllers;

use App\Enums\Bucket;
use App\Enums\ReviewSource;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\TransactionReviewService;
use App\Support\DemoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionReviewService $reviewService,
        private readonly DemoUserContext $demoUser,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $this->demoUser->user();
        $query = Transaction::query()
            ->forUser($user)
            ->with(['account', 'canonicalMerchant', 'category'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

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
            $query->where(function ($builder) use ($search): void {
                $builder->where('merchant', 'like', "%{$search}%")
                    ->orWhere('raw_merchant_descriptor', 'like', "%{$search}%")
                    ->orWhereHas('canonicalMerchant', fn ($merchantQuery) => $merchantQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->boolean('paginate', true) && ! $request->boolean('unreviewed_only') && $request->query('queue') !== 'review') {
            return TransactionResource::collection($query->paginate(25));
        }

        return TransactionResource::collection($query->get());
    }

    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $user = $this->demoUser->user();
        $data = $request->validated();
        $reviewed = (bool) ($data['reviewed'] ?? false);
        $categoryId = $data['category_id'] ?? null;
        $bucket = $data['bucket'] ?? null;
        $subcategory = $data['subcategory'] ?? null;

        if ($categoryId !== null) {
            $category = Category::query()->findOrFail($categoryId);
            $bucket = $category->bucket->value;
            $subcategory = $category->name;
        }

        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'account_id' => $data['account_id'] ?? null,
            'merchant' => $data['merchant'],
            'raw_merchant_descriptor' => $data['merchant'],
            'amount_cents' => $data['amount_cents'],
            'kind' => $data['kind'],
            'bucket' => $bucket,
            'subcategory' => $subcategory,
            'category_id' => $categoryId,
            'transaction_date' => $data['transaction_date'],
            'notes' => $data['notes'] ?? null,
            'reviewed_at' => $reviewed ? now() : null,
            'review_source' => $reviewed ? ReviewSource::Manual : null,
        ]);

        return (new TransactionResource($transaction->load(['account', 'canonicalMerchant', 'category'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateTransactionRequest $request,
        Transaction $transaction,
    ): TransactionResource {
        $this->ensureOwned($transaction);

        $data = $request->validated();
        $attributes = collect($data)->except(['reviewed', 'category'])->all();

        if (array_key_exists('merchant', $attributes) && ! array_key_exists('raw_merchant_descriptor', $attributes)) {
            $attributes['raw_merchant_descriptor'] = $attributes['merchant'];
        }

        if (array_key_exists('bucket', $attributes) && ! array_key_exists('category_id', $attributes)) {
            if ($attributes['bucket'] === null) {
                $attributes['category_id'] = null;
                $attributes['subcategory'] ??= null;
            } else {
                $selectedBucket = $attributes['bucket'] instanceof Bucket
                    ? $attributes['bucket']
                    : Bucket::from($attributes['bucket']);
                $currentCategory = $transaction->category;

                if ($currentCategory === null || $currentCategory->bucket !== $selectedBucket) {
                    $attributes['category_id'] = null;
                    $attributes['subcategory'] ??= null;
                }
            }
        }

        if (
            array_key_exists('category_id', $attributes)
            && $attributes['category_id'] === null
            && ! array_key_exists('subcategory', $attributes)
        ) {
            $attributes['subcategory'] = null;
        }

        if (array_key_exists('category_id', $attributes) && $attributes['category_id'] !== null) {
            $category = Category::query()->findOrFail($attributes['category_id']);
            $attributes['bucket'] = $category->bucket->value;
            $attributes['subcategory'] = $category->name;
        }

        if (array_key_exists('reviewed', $data) && $data['reviewed'] === true) {
            $bucketValue = $attributes['bucket'] ?? $data['bucket'] ?? $transaction->bucket?->value;

            if ($bucketValue === null) {
                throw new UnprocessableEntityHttpException('A bucket is required to mark a transaction reviewed.');
            }

            if ($transaction->isReviewed()) {
                $transaction->fill($attributes);
                $transaction->save();
            } else {
                $transaction->fill(collect($attributes)->except(['bucket', 'subcategory', 'category_id'])->all());
                $transaction->save();

                $transaction = $this->reviewService->review(
                    transaction: $transaction,
                    bucket: $bucketValue instanceof Bucket ? $bucketValue : Bucket::from($bucketValue),
                    subcategory: $attributes['subcategory'] ?? $data['subcategory'] ?? $transaction->subcategory,
                    source: ReviewSource::Manual,
                    confidence: 100,
                    explanation: 'Manually reviewed.',
                    categoryId: array_key_exists('category_id', $attributes)
                        ? $attributes['category_id']
                        : $transaction->category_id,
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

        return new TransactionResource($transaction->load(['account', 'canonicalMerchant', 'category']));
    }

    public function undo(Transaction $transaction): TransactionResource
    {
        $this->ensureOwned($transaction);

        return new TransactionResource(
            $this->reviewService->undo($transaction)->load(['account', 'canonicalMerchant', 'category']),
        );
    }

    public function suggestion(Transaction $transaction): JsonResponse
    {
        $this->ensureOwned($transaction);

        $result = $this->reviewService->suggest($transaction);

        return response()->json([
            'data' => [
                'bucket' => $result->bucket?->value,
                'subcategory' => $result->subcategory,
                'category_id' => $result->categoryId,
                'confidence' => $result->confidence,
                'source' => $result->source->value,
                'explanation' => $result->explanation,
                'auto_review' => $result->autoReview,
            ],
        ]);
    }

    private function ensureOwned(Transaction $transaction): void
    {
        if ((int) $transaction->user_id !== $this->demoUser->id()) {
            throw new NotFoundHttpException('Transaction not found.');
        }
    }
}
