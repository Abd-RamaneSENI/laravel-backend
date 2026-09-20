<?php

namespace App\Policies;

use App\Models\Resource;
use App\Models\User;

class ResourcePolicy
{
    public function update(User $user, Resource $resource): bool
    {
        return $user->isAdmin() || ($user->isVendor() && $resource->author_id === $user->id);
    }

    public function download(User $user, Resource $resource): bool
    {
        return $user->entitlements()->where('resource_id', $resource->id)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists();
    }
}
