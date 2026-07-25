<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategorizationRuleRequest;
use App\Http\Requests\UpdateCategorizationRuleRequest;
use App\Http\Resources\CategorizationRuleResource;
use App\Models\CategorizationRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CategorizationRuleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CategorizationRuleResource::collection(
            CategorizationRule::query()->orderBy('priority')->orderBy('id')->get()
        );
    }

    public function store(StoreCategorizationRuleRequest $request): JsonResponse
    {
        $rule = CategorizationRule::query()->create($request->validated());

        return (new CategorizationRuleResource($rule))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateCategorizationRuleRequest $request,
        CategorizationRule $categorizationRule,
    ): CategorizationRuleResource {
        $categorizationRule->update($request->validated());

        return new CategorizationRuleResource($categorizationRule->refresh());
    }

    public function destroy(CategorizationRule $categorizationRule): Response
    {
        $categorizationRule->delete();

        return response()->noContent();
    }
}
