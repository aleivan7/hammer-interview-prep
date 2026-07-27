<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\CategorizationRule;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\DemoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CategoryController extends Controller
{
    public function __construct(
        private readonly DemoUserContext $demoUser,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::query()
                ->visibleTo($this->demoUser->user())
                ->active()
                ->orderBy('bucket')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
        );
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        $category = Category::query()->create([
            'user_id' => $this->demoUser->id(),
            'bucket' => $data['bucket'],
            'name' => trim($data['name']),
            'sort_order' => 1000,
            'archived_at' => null,
        ]);

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $this->ensureOwnedCustom($category);

        $data = $request->validated();

        DB::transaction(function () use ($category, $data): void {
            if (array_key_exists('name', $data)) {
                $category->name = trim($data['name']);
            }

            if (array_key_exists('bucket', $data)) {
                $category->bucket = $data['bucket'];
            }

            if (array_key_exists('archived', $data)) {
                $category->archived_at = $data['archived'] ? now() : null;
            }

            $category->save();

            if ($category->wasChanged(['name', 'bucket'])) {
                Transaction::query()
                    ->where('category_id', $category->id)
                    ->update([
                        'bucket' => $category->bucket->value,
                        'subcategory' => $category->name,
                    ]);
                CategorizationRule::query()
                    ->where('category_id', $category->id)
                    ->update([
                        'target_bucket' => $category->bucket->value,
                        'target_subcategory' => $category->normalized_name,
                    ]);
            }
        });

        return new CategoryResource($category->refresh());
    }

    public function destroy(Category $category): Response
    {
        $this->ensureOwnedCustom($category);

        if ($category->isSystem()) {
            throw new UnprocessableEntityHttpException('System categories cannot be archived through delete.');
        }

        $category->update(['archived_at' => now()]);

        return response()->noContent();
    }

    private function ensureOwnedCustom(Category $category): void
    {
        if ($category->isSystem() || (int) $category->user_id !== $this->demoUser->id()) {
            throw new NotFoundHttpException('Category not found.');
        }
    }
}
