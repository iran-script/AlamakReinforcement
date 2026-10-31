<?php

namespace App\Policies;

use App\Models\FormDefinition;
use App\Models\User;
use App\Services\AccessPolicyService;

class FormDefinitionPolicy
{
    public function __construct(private AccessPolicyService $a) {}

    public function viewAny(User $u): bool
    {
        return $this->a->can($u, 'form_definitions', 'view');
    }

    public function create(User $u): bool
    {
        return $this->a->can($u, 'form_definitions', 'configure');
    }

    public function view(User $u, FormDefinition $m): bool
    {
        return ($m->organization_id === null || $this->owns($u, $m)) && $this->a->can($u, 'form_definitions', 'view');
    }

    public function update(User $u, FormDefinition $m): bool
    {
        return $this->owns($u, $m) && $this->a->can($u, 'form_definitions', 'configure');
    }

    public function publish(User $u, FormDefinition $m): bool
    {
        return $this->owns($u, $m) && $this->a->can($u, 'form_definitions', 'publish');
    }

    private function owns(User $u, FormDefinition $m): bool
    {
        return $u->hasRole('superadmin') || ($u->organization_id !== null && $m->organization_id !== null && (int) $u->organization_id === (int) $m->organization_id);
    }
}
