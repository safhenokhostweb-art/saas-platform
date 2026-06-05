<?php

namespace SaaSPlatform\Modules\Education\Policies;

use SaaSPlatform\Modules\Education\Models\EducationWalkthrough;

class EducationWalkthroughPolicy
{
    public function viewAny($user): bool
    {
        return $user->is_admin || $user->can('education.view');
    }

    public function view($user, EducationWalkthrough $walkthrough): bool
    {
        return $user->is_admin || $user->can('education.view');
    }

    public function create($user): bool
    {
        return $user->is_admin || $user->can('education.create');
    }

    public function update($user, EducationWalkthrough $walkthrough): bool
    {
        return $user->is_admin || $user->can('education.update');
    }

    public function delete($user, EducationWalkthrough $walkthrough): bool
    {
        return $user->is_admin || $user->can('education.delete');
    }
}
