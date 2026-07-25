<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\DemoUserContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveDemoUser
{
    public function __construct(
        private readonly DemoUserContext $demoUser,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('X-Demo-User');

        if ($header === null || trim($header) === '') {
            return response()->json([
                'message' => 'A demo user must be selected.',
                'code' => 'demo_user_required',
            ], 401);
        }

        if (! ctype_digit($header) || (int) $header < 1) {
            return response()->json([
                'message' => 'The selected demo user is invalid.',
                'code' => 'demo_user_invalid',
            ], 401);
        }

        $user = User::query()
            ->whereKey((int) $header)
            ->whereNotNull('persona_type')
            ->first();

        if ($user === null) {
            return response()->json([
                'message' => 'The selected demo user is invalid.',
                'code' => 'demo_user_invalid',
            ], 401);
        }

        $this->demoUser->set($user);
        $request->attributes->set('demo_user', $user);

        return $next($request);
    }
}
