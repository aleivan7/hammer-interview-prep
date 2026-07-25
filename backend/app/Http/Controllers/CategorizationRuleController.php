<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategorizationRuleRequest;
use App\Http\Requests\UpdateCategorizationRuleRequest;
use App\Http\Resources\CategorizationRuleResource;
use App\Models\CategorizationRule;
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
                ->orderBy('priority')
                ->orderBy('id')
                ->get()
        );
    }

    public function store(StoreCategorizationRuleRequest $request): JsonResponse
    {
        $rule = CategorizationRule::query()->create([
            ...$request->validated(),
            'user_id' => $this->demoUser->id(),
        ]);

        return (new CategorizationRuleResource($rule))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateCategorizationRuleRequest $request,
        CategorizationRule $categorizationRule,
    ): CategorizationRuleResource {
        $this->ensureOwned($categorizationRule);

        $categorizationRule->update($request->validated());

        return new CategorizationRuleResource($categorizationRule->refresh());
    }

    public function destroy(CategorizationRule $categorizationRule): Response
    {
        $this->ensureOwned($categorizationRule);

        $categorizationRule->delete();

        return response()->noContent();
    }

    private function ensureOwned(CategorizationRule $rule): void
    {
        if ((int) $rule->user_id !== $this->demoUser->id()) {
            throw new NotFoundHttpException('Categorization rule not found.');
        }
    }
}
