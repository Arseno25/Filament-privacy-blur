<?php

namespace Arseno25\FilamentPrivacyBlur\Services;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class PrivacyAuthorizationService
{
    /**
     * Check if a user should be hidden from privacy (forced blur).
     */
    public static function isHidden(?array $hiddenRoles = null): bool
    {
        $user = auth()->user();

        if (!$user || $hiddenRoles === null || count($hiddenRoles) === 0) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole($hiddenRoles)) {
            return true;
        }

        return false;
    }

    /**
     * Check if a user is authorized based on roles, permissions, policies, or a custom closure.
     */
    public static function isAuthorized(
        ?array $roles = null,
        ?array $permissions = null,
        ?string $policy = null,
        ?Closure $customAuth = null,
        ?Model $record = null
    ): bool {
        $user = auth()->user();

        // 1. If no user, they can't be authorized
        if (! $user) {
            return false;
        }

        // 2. Custom Auth Closure
        if ($customAuth !== null) {
            return (bool) app()->call($customAuth, ['user' => $user, 'record' => $record]);
        }

        // 3. Spatie Permissions / Roles (if roles specified)
        if ($roles !== null && count($roles) > 0) {
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles)) {
                return true;
            }
        }

        // 4. Permissions Check
        if ($permissions !== null && count($permissions) > 0) {
            if (method_exists($user, 'hasAnyPermission') && $user->hasAnyPermission($permissions)) {
                return true;
            }
            // Fallback to can()
            foreach ($permissions as $permission) {
                if ($user->can($permission)) {
                    return true;
                }
            }
        }

        // 5. Policy/Gate Check
        if ($policy !== null) {
            if (Gate::has($policy)) {
                return Gate::allows($policy, $record);
            }

            // Assume it's an action on the model
            if ($record) {
                return $user->can($policy, $record);
            }

            return $user->can($policy);
        }

        // If no strict rules defined and no exceptions raised,
        // fallback to whether the user is generally allowed or not.
        // We will default to FALSE if the developer specifically injected an auth check
        // but it didn't match. If none of the arrays are provided, it implies NO auth was passed,
        // meaning we shouldn't grant authorization simply because none was requested.
        // We only get here if they defined roles/permissions but they failed, OR if none were defined.
        return false;
    }
}
