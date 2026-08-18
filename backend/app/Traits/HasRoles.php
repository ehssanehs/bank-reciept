<?php

namespace App\Traits;

use App\Models\Permission;
use App\Models\Role;

/**
 * Lightweight RBAC: a user has many roles; each role has many permissions.
 * Permissions are enforced by the `EnsureRole` middleware and by policies
 * checking `hasPermissionTo`.
 */
trait HasRoles
{
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    public function assignRole(string|Role $role): self
    {
        if (is_string($role)) {
            $role = Role::query()->where('slug', $role)->firstOrFail();
        }
        $this->roles()->syncWithoutDetaching([$role->getKey()]);

        return $this;
    }

    public function hasRole(string ...$slugs): bool
    {
        return $this->roles()->whereIn('slug', $slugs)->exists();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN);
    }

    /**
     * Returns the permission slugs available to this user (via all roles).
     *
     * @return array<int,string>
     */
    public function permissionSlugs(): array
    {
        return $this->roles()
            ->with('permissions')
            ->get()
            ->flatMap(fn (Role $r) => $r->permissions->pluck('slug'))
            ->unique()
            ->values()
            ->all();
    }

    public function hasPermissionTo(string $permissionSlug): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($permissionSlug, $this->permissionSlugs(), true);
    }

    public function hasAnyPermission(array $slugs): bool
    {
        foreach ($slugs as $slug) {
            if ($this->hasPermissionTo($slug)) {
                return true;
            }
        }

        return false;
    }
}
