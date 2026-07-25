<?php

namespace App\Http\Controllers;

use App\Services\SmartReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmartReviewController extends Controller
{
    public function __construct(
        private readonly SmartReviewService $smartReview,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'batch_key' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                'regex:/\A[A-Za-z0-9-]+\z/',
            ],
        ]);

        $result = $this->smartReview->run($validated['batch_key'] ?? null);

        return response()->json(['data' => $result]);
    }
}
