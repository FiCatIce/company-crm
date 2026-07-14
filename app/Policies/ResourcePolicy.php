<?php

namespace App\Policies;

use App\Models\User;

/**
 * Permission-based authorization shared by the CRM's domain resources. Each
 * subclass declares which permissions grant each action; the checks themselves
 * go through Spatie ($user->can('<permission>')) — never role names.
 *
 * Scope (all vs own) is expressed by the *set* of view/update permissions a
 * subclass returns; the actual row filtering it implies is applied separately at
 * the query layer (Model::visibleTo, from B1). In this batch every user with a
 * management role holds the ".all" permissions, so access is unchanged.
 */
abstract class ResourcePolicy
{
    /**
     * Permissions that grant viewing the resource (any one suffices).
     *
     * @return list<string>
     */
    abstract protected function viewPermissions(): array;

    abstract protected function createPermission(): string;

    /**
     * Permissions that grant updating the resource (any one suffices).
     *
     * @return list<string>
     */
    abstract protected function updatePermissions(): array;

    abstract protected function deletePermission(): string;

    public function viewAny(User $user): bool
    {
        return $this->hasAny($user, $this->viewPermissions());
    }

    public function view(User $user): bool
    {
        return $this->hasAny($user, $this->viewPermissions());
    }

    public function create(User $user): bool
    {
        return $user->can($this->createPermission());
    }

    public function update(User $user): bool
    {
        return $this->hasAny($user, $this->updatePermissions());
    }

    public function delete(User $user): bool
    {
        return $user->can($this->deletePermission());
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function hasAny(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
