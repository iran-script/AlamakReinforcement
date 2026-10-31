<?php

namespace App\Policies;

use App\Models\FormSubmission;
use App\Models\User;
use App\Services\AccessPolicyService;

class FormSubmissionPolicy
{
    public function __construct(private AccessPolicyService $a) {}

    public function viewAny(User $u): bool
    {
        return $this->a->can($u, 'form_submissions', 'view');
    }

    public function create(User $u): bool
    {
        return $this->a->can($u, 'form_submissions', 'submit');
    }

    public function view(User $u, FormSubmission $s): bool
    {
        return $this->owns($u, $s) && $this->a->can($u, 'form_submissions', 'view');
    }

    public function update(User $u, FormSubmission $s): bool
    {
        return $this->owns($u, $s) && $s->status->value === 'draft' && $this->a->can($u, 'form_submissions', 'submit');
    }

    public function upload(User $u, FormSubmission $s): bool
    {
        return $this->owns($u, $s) && $s->status->value === 'draft' && $this->a->can($u, 'form_submissions', 'upload');
    }

    private function owns(User $u, FormSubmission $s): bool
    {
        return $u->hasRole('superadmin') || ($u->organization_id !== null && (int) $u->organization_id === (int) $s->organization_id);
    }
}
