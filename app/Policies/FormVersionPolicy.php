<?php

namespace App\Policies;

use App\Models\FormVersion;
use App\Models\User;

class FormVersionPolicy
{
    public function view(User $u, FormVersion $v): bool
    {
        return $u->can('view', $v->definition);
    }

    public function update(User $u, FormVersion $v): bool
    {
        return $v->isMutable() && $u->can('update', $v->definition);
    }

    public function publish(User $u, FormVersion $v): bool
    {
        return $u->can('publish', $v->definition);
    }
}
