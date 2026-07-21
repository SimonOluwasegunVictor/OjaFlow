<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToBusiness
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        if (!$user->business_id) {
            return response()->json([
                'message' => 'Your account is not attached to a business',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $route = $request->route();

        if ($route) {
            foreach ($route->parameters() as $name => $parameter) {
                if ($this->isUserRouteParameter($name)) {
                    $targetUser = $parameter instanceof User
                        ? $parameter
                        : User::query()->find($parameter);

                    if (!$targetUser) {
                        return response()->json([
                            'message' => 'User not found',
                        ], JsonResponse::HTTP_NOT_FOUND);
                    }

                    if ($targetUser->business_id !== $user->business_id) {
                        return response()->json([
                            'message' => 'This user does not belong to your business',
                        ], JsonResponse::HTTP_FORBIDDEN);
                    }
                }

                if ($parameter instanceof Model && $this->hasBusinessId($parameter)) {
                    if ($parameter->getAttribute('business_id') !== $user->business_id) {
                        return response()->json([
                            'message' => 'This resource does not belong to your business',
                        ], JsonResponse::HTTP_FORBIDDEN);
                    }
                }
            }
        }

        return $next($request);
    }

    private function isUserRouteParameter(string $name): bool
    {
        return in_array($name, ['user', 'userId', 'staff', 'staffId'], true);
    }

    private function hasBusinessId(Model $model): bool
    {
        return array_key_exists('business_id', $model->getAttributes());
    }
}
