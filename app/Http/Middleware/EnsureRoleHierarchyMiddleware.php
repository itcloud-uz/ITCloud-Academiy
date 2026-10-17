<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoleHierarchyMiddleware
{
    /**
     * Enforces strict RBAC boundaries between 'admin', 'ai-mentor', and 'student' roles
     * using Laravel Sanctum abilities + Spatie Permission roles.
     */
    public function handle(Request $request, Closure $next, string ...$allowedRoles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated request.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $user->hasAnyRole($allowedRoles)) {
            return response()->json([
                'message' => 'Forbidden: Insufficient RBAC clearance for this resource.',
                'required_roles' => $allowedRoles,
            ], Response::HTTP_FORBIDDEN);
        }

        // Additional Tenant/Row isolation for Student role
        if ($user->hasRole('student') && $request->route('student')) {
            $routeStudentId = (string) $request->route('student');
            $ownStudentId = (string) optional($user->student)->id;

            if ($routeStudentId !== $ownStudentId) {
                return response()->json([
                    'message' => 'Security violation: Cross-student access is strictly prohibited.',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        return $next($request);
    }
}
