<?php

namespace Arseno25\FilamentPrivacyBlur\Services;

use Arseno25\FilamentPrivacyBlur\DataTransferObjects\AuthorizationResult;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class PrivacyAuthorizationService
{
    /**
     * Check authorization using Laravel Gate/Policy as primary mechanism.
     * This is the ability-first approach.
     *
     * @param  string  $ability  The gate ability or policy method to check
     * @param  Model|null  $record  Optional model instance for policy checks
     */
    public static function authorizeWith(
        string $ability,
        ?Model $record = null
    ): AuthorizationResult {
        $user = auth()->user();

        if (! $user) {
            return AuthorizationResult::noUser();
        }

        // Try Gate first (works for both gates and policies)
        if (Gate::has($ability)) {
            $authorized = Gate::allows($ability, $record);

            return $authorized
                ? AuthorizationResult::authorized('gate', 'gate_allowed')
                : AuthorizationResult::denied('gate', 'gate_denied');
        }

        // Fallback to user->can()
        $authorized = $record
            ? $user->can($ability, $record)
            : $user->can($ability);

        return new AuthorizationResult(
            authorized: $authorized,
            method: 'can',
            reason: $authorized ? 'can_allowed' : 'can_denied'
        );
    }

    /**
     * Check authorization using custom closure with full context.
     */
    public static function authorizeUsing(
        Closure $callback,
        ?Model $record = null
    ): AuthorizationResult {
        $user = auth()->user();

        if (! $user) {
            return AuthorizationResult::noUser();
        }

        $authorized = (bool) app()->call($callback, [
            'user' => $user,
            'record' => $record,
        ]);

        return new AuthorizationResult(
            authorized: $authorized,
            method: 'closure',
            reason: $authorized ? 'closure_allowed' : 'closure_denied'
        );
    }

    /**
     * Check permissions via Laravel's can() method.
     * This works with Spatie, Shield, or any Gate integration.
     *
     * @param  array<string>  $permissions  List of permissions to check (any match)
     */
    public static function authorizeWithPermissions(
        array $permissions
    ): AuthorizationResult {
        $user = auth()->user();

        if (! $user) {
            return AuthorizationResult::noUser();
        }

        if (empty($permissions)) {
            return AuthorizationResult::denied('permissions', 'no_permissions');
        }

        // Try Spatie's hasAnyPermission first (if available)
        if (method_exists($user, 'hasAnyPermission')) {
            $authorized = $user->hasAnyPermission($permissions);

            return $authorized
                ? AuthorizationResult::authorized('spatie_permissions', 'spatie_allowed')
                : AuthorizationResult::denied('spatie_permissions', 'spatie_denied');
        }

        // Try Spatie's hasPermissionTo (alternative method name)
        if (method_exists($user, 'hasPermissionTo')) {
            foreach ($permissions as $permission) {
                try {
                    if ($user->hasPermissionTo($permission)) {
                        return AuthorizationResult::authorized('spatie_permissions', 'has_permission_to_allowed');
                    }
                } catch (\Throwable $e) {
                    // Continue to can() fallback
                }
            }
        }

        // Fallback to Laravel's can() for each permission
        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return AuthorizationResult::authorized('can', 'can_allowed');
            }
        }

        return AuthorizationResult::denied('can', 'all_can_denied');
    }

    /**
     * Check roles - convenience wrapper, NOT core safety mechanism.
     * Degrades gracefully if role methods don't exist.
     *
     * @param  array<string>  $roles  List of roles to check (any match)
     */
    public static function authorizeWithRoles(
        array $roles
    ): AuthorizationResult {
        $user = auth()->user();

        if (! $user) {
            return AuthorizationResult::noUser();
        }

        if (empty($roles)) {
            return AuthorizationResult::denied('roles', 'no_roles');
        }

        // Try various role checking methods (Spatie, custom, etc.)
        $methods = [
            'hasAnyRole' => true,   // Check any of the roles
            'hasRole' => false,     // Will check first role only
            'hasExactRole' => true, // Some packages use this
        ];

        foreach ($methods as $method => $isArray) {
            if (method_exists($user, $method)) {
                if ($isArray) {
                    $authorized = $user->{$method}($roles);
                } else {
                    // For hasRole, check each role individually
                    $authorized = false;
                    foreach ($roles as $role) {
                        if ($user->{$method}($role)) {
                            $authorized = true;
                            break;
                        }
                    }
                }

                return $authorized
                    ? AuthorizationResult::authorized("roles_{$method}", 'role_allowed')
                    : AuthorizationResult::denied("roles_{$method}", 'role_denied');
            }
        }

        // No role method found - this is OK, roles are optional
        return AuthorizationResult::denied('roles', 'no_role_method_available');
    }

    /**
     * Check if user is in hidden roles (forced blur).
     *
     * @param  array<string>|null  $hiddenRoles  Roles that should be forced to see blur
     */
    public static function isHidden(?array $hiddenRoles = null): bool
    {
        $user = auth()->user();

        if (! $user || $hiddenRoles === null || count($hiddenRoles) === 0) {
            return false;
        }

        // Try multiple role checking methods
        $methods = ['hasAnyRole', 'hasRole', 'hasExactRole'];
        foreach ($methods as $method) {
            if (method_exists($user, $method)) {
                // hasAnyRole accepts array, hasRole usually accepts string
                if ($method === 'hasAnyRole') {
                    if ($user->{$method}($hiddenRoles)) {
                        return true;
                    }
                } else {
                    // Check each role individually
                    foreach ($hiddenRoles as $role) {
                        if ($user->{$method}($role)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check authorization based on metadata array from PrivacyMetadataHelper.
     * This is the primary method used by ColumnPrivacyMacros.
     *
     * Priority order:
     * 1. authorizeRevealUsing (custom closure)
     * 2. authorizeRevealWith (Gate/Policy ability)
     * 3. permission (via can())
     * 4. visibleToPermissions (multiple permissions via can())
     * 5. visibleToRoles (convenience)
     * 6. policy string (legacy)
     *
     * @param  array<string, mixed>  $meta  Metadata from PrivacyMetadataHelper
     * @param  Model|null  $record  The data record for context
     */
    public static function checkAuthorization(array $meta, ?Model $record = null): bool
    {
        // Priority 1: Custom closure (authorizeRevealUsing, authorizeUsing)
        if (isset($meta['privacy_auth_closure'])) {
            return self::authorizeUsing(
                $meta['privacy_auth_closure'],
                $record
            )->authorized;
        }

        // Priority 2: Gate/Policy ability (authorizeRevealWith)
        if (isset($meta['privacy_ability'])) {
            return self::authorizeWith(
                $meta['privacy_ability'],
                $meta['privacy_auth_record'] ?? $record
            )->authorized;
        }

        // Priority 3: Single permission (permission)
        if (isset($meta['privacy_permission']) && is_string($meta['privacy_permission'])) {
            return self::authorizeWithPermissions([$meta['privacy_permission']])->authorized;
        }

        // Priority 4: Multiple permissions (visibleToPermissions)
        if (isset($meta['privacy_permissions']) && is_array($meta['privacy_permissions'])) {
            return self::authorizeWithPermissions($meta['privacy_permissions'])->authorized;
        }

        // Priority 5: Roles (convenience)
        if (isset($meta['privacy_roles']) && is_array($meta['privacy_roles'])) {
            return self::authorizeWithRoles($meta['privacy_roles'])->authorized;
        }

        // Priority 6: Legacy policy string (policy, privacyPolicy)
        if (isset($meta['privacy_policy']) && is_string($meta['privacy_policy'])) {
            return self::authorizeWith($meta['privacy_policy'], $record)->authorized;
        }

        return false; // Secure by default
    }

    /**
     * Legacy method for backward compatibility.
     *
     * @deprecated Use checkAuthorization() with metadata array instead.
     */
    public static function isAuthorized(
        ?array $roles = null,
        ?array $permissions = null,
        ?string $policy = null,
        ?Closure $customAuth = null,
        ?Model $record = null
    ): bool {
        // Check for explicit authorization constraints
        $hasAnyConstraint = ($roles !== null && count($roles) > 0)
            || ($permissions !== null && count($permissions) > 0)
            || ($policy !== null)
            || ($customAuth !== null);

        if (! $hasAnyConstraint) {
            return false; // Secure by default
        }

        // Build metadata array and use checkAuthorization
        $meta = [];

        if ($roles !== null && count($roles) > 0) {
            $meta['privacy_roles'] = $roles;
        }

        if ($permissions !== null && count($permissions) > 0) {
            $meta['privacy_permissions'] = $permissions;
        }

        if ($policy !== null) {
            $meta['privacy_policy'] = $policy;
        }

        if ($customAuth !== null) {
            $meta['privacy_auth_closure'] = $customAuth;
        }

        return self::checkAuthorization($meta, $record);
    }
}
