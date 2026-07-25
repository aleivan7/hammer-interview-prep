<?php

namespace App\Http\Controllers;

use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Support\DemoUserContext;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountController extends Controller
{
    public function __construct(
        private readonly DemoUserContext $demoUser,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return AccountResource::collection(
            Account::query()
                ->forUser($this->demoUser->user())
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
        );
    }
}
