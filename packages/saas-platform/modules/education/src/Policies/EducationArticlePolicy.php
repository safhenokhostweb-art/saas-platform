<?php

namespace SaaSPlatform\Modules\Education\Policies;

use SaaSPlatform\Modules\Education\Models\EducationArticle;

class EducationArticlePolicy
{
    public function viewAny($user): bool
    {
        return $user->is_admin || $user->can('education.view');
    }

    public function view($user, EducationArticle $article): bool
    {
        if ($user->is_admin) {
            return true;
        }
        if ($article->status !== EducationArticle::STATUS_PUBLISHED) {
            return false;
        }
        if (! empty($article->target_roles) && ! in_array($user->role ?? '', $article->target_roles, true)) {
            return false;
        }
        return true;
    }

    public function create($user): bool
    {
        return $user->is_admin || $user->can('education.create');
    }

    public function update($user, EducationArticle $article): bool
    {
        return $user->is_admin || $user->can('education.update');
    }

    public function delete($user, EducationArticle $article): bool
    {
        return $user->is_admin || $user->can('education.delete');
    }
}
