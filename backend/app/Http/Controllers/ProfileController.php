<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProfileResource;
use App\Services\DemoPersonaDataService;
use App\Support\DemoUserContext;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function __construct(
        private readonly DemoUserContext $demoUser,
        private readonly DemoPersonaDataService $personaData,
    ) {}

    public function show(): ProfileResource
    {
        $user = $this->demoUser->user()->load([
            'financialPlan',
            'accounts' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
        ]);

        return new ProfileResource($user);
    }

    public function reset(): JsonResponse
    {
        $user = $this->personaData->resetFinancialData($this->demoUser->user());

        return response()->json([
            'message' => 'Demo profile data restored to its original seeded state.',
            'data' => (new ProfileResource($user))->resolve(),
        ]);
    }
}
