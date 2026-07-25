<?php

namespace App\Http\Controllers;

use App\Http\Resources\DemoUserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DemoUserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $users = User::query()
            ->whereNotNull('persona_type')
            ->with(['financialPlan'])
            ->withCount('accounts')
            ->orderBy('id')
            ->get();

        return DemoUserResource::collection($users);
    }
}
