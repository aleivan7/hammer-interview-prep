<?php

namespace App\Http\Controllers;

use App\Http\Resources\MerchantResource;
use App\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MerchantController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = $request->query('search');

        return MerchantResource::collection(
            Merchant::query()
                ->with(['aliases' => fn ($query) => $query->orderBy('priority')->orderBy('id')])
                ->search(is_string($search) ? $search : null)
                ->orderBy('name')
                ->orderBy('id')
                ->get()
        );
    }
}
