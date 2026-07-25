<?php

namespace App\Http\Controllers;

use App\Http\Resources\AccountResource;
use App\Models\Account;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AccountResource::collection(
            Account::query()->orderBy('sort_order')->orderBy('id')->get()
        );
    }
}
