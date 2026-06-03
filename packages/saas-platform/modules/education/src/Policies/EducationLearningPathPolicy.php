<?php

namespace SaaSPlatform\Modules\Education\Policies;

use SaaSPlatform\Modules\Education\Models\EducationLearningPath;

class EducationLearningPathPolicy
{
    public function viewAny($user): bool
    {
        return $user->is_admin || $user->can('education.view');
    }

    public function view($user, EducationLearningPath $path): bool
    {
        if ($user->is_admin) {
            return true;
        }
        if (! $path->is_active) {
            return false;
        }
        if (! empty($path->target_roles) && ! in_array($user->role ?? '', $path->target_roles, true)) {
            return false;
        }
        return true;
    }

    public function create($user): bool
    {
        return $user->is_admin || $user->can('education.create');
    }

    public function update($user, EducationLearningPath $path): bool
    {
        return $user->is_admin || $user->can('education.update');
    }

    public function delete($user, EducationLearningPath $path): bool
    {
        return $user->is_admin || $user->can('education.delete');
    }
}
