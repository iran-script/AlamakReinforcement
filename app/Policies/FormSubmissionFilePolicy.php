<?php

namespace App\Policies;

use App\Models\FormSubmissionFile;
use App\Models\User;

class FormSubmissionFilePolicy
{
    public function view(User $u, FormSubmissionFile $f): bool
    {
        return $u->can('view', $f->submission);
    }

    public function delete(User $u, FormSubmissionFile $f): bool
    {
        return $u->can('upload', $f->submission);
    }
}
