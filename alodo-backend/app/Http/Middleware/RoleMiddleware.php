<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Role;
class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next,string $role): Response
    {
        $roleId = Role::where('name', '=', $role)->value('id');
        if ($request->user()->role_id != $roleId) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }
        return $next($request);
    }
}
