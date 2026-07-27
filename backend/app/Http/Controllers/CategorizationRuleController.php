<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategorizationRuleRequest;
use App\Http\Requests\UpdateCategorizationRuleRequest;
use App\Http\Resources\CategorizationRuleResource;
use App\Models\CategorizationRule;
use App\Models\Category;
use App\Models\Merchant;
use App\Support\DemoUserContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategorizationRuleController extends Controller
{
    public function __construct(
        private readonly DemoUserContext $demoUser,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return CategorizationRuleResource::collection(
            CategorizationRule::query()
                ->forUser($this->demoUser->user())
                ->with(['merchant', 'category'])
                ->orderBy('priority')
                ->orderBy('id')
                ->get()
        );
    }

    public function store(StoreCategorizationRuleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $attributes = $this->attributesWithCatalogDerivations($data);

        $rule = CategorizationRule::query()->create([
            ...$attributes,
            'user_id' => $this->demoUser->id(),
        ]);

        return (new CategorizationRuleResource($rule->load(['merchant', 'category'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateCategorizationRuleRequest $request,
        CategorizationRule $categorizationRule,
    ): CategorizationRuleResource {
        $this->ensureOwned($categorizationRule);

        $data = $request->validated();
        $attributes = $this->attributesWithCatalogDerivations($data, $categorizationRule);

        $categorizationRule->update($attributes);

        return new CategorizationRuleResource(
            $categorizationRule->refresh()->load(['merchant', 'category']),
        );
    }

    public function destroy(CategorizationRule $categorizationRule): Response
    {
        $this->ensureOwned($categorizationRule);

        $categorizationRule->delete();

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributesWithCatalogDerivations(
        array $data,
        ?CategorizationRule $existing = null,
    ): array {
        $merchantId = array_key_exists('merchant_id', $data)
            ? $data['merchant_id']
            : $existing?->merchant_id;

        if (
            $merchantId !== null
            && (! array_key_exists('merchant_contains', $data) || blank($data['merchant_contains']))
        ) {
            $merchant = Merchant::query()->findOrFail($merchantId);
            $data['merchant_contains'] = mb_strtolower($merchant->name);
        }

        $categoryId = array_key_exists('category_id', $data)
            ? $data['category_id']
            : $existing?->category_id;

        if ($categoryId !== null) {
            $category = Category::query()->findOrFail($categoryId);
            $data['target_bucket'] = $category->bucket->value;
            $data['target_subcategory'] = $category->normalized_name;
        }

        return $data;
    }

    private function ensureOwned(CategorizationRule $rule): void
    {
        if ((int) $rule->user_id !== $this->demoUser->id()) {
            throw new NotFoundHttpException('Categorization rule not found.');
        }
    }
}
