<?php

namespace SaaSPlatform\Modules\Education\Policies;

use SaaSPlatform\Modules\Education\Models\EducationArticle;

class EducationArticlePolicy
{
    public function viewAny($user): bool
    {
        return $user->is_admin || $user->can('education.view');
    }

    /**
     * Whether the user may access AI-generated content review metrics.
     *
     * Allowed: platform admins and site admins (is_admin = true).
     * Forbidden: ordinary authenticated users.
     *
     * Site scoping (own site only) is enforced at the query level in the
     * controller via the tenant_site request attribute — not in this policy.
     */
    public function viewGeneratedContent($user): bool
    {
        return (bool) $user->is_admin;
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

    // -------------------------------------------------------------------------
    // EDU-16 review abilities
    // -------------------------------------------------------------------------

    /**
     * Whether the user may approve (publish) an auto-generated draft article.
     *
     * Rules:
     *   - User must be an admin.
     *   - Platform admin (no site_id): may approve ANY auto-generated draft.
     *   - Site admin (has site_id): may only approve drafts belonging to their site
     *     OR platform-level drafts (site_id IS NULL).
     *   - Article must be auto_generated=true (enforced in service, not policy).
     */
    public function approveGeneratedContent($user, EducationArticle $article): bool
    {
        if (! $user->is_admin) {
            return false;
        }

        // Platform admin: may approve any draft
        if (empty($user->site_id)) {
            return true;
        }

        // Site admin: only their site's drafts or platform-level drafts
        return $article->site_id === null
            || $article->site_id === (int) $user->site_id;
    }

    /**
     * Whether the user may reject (archive) an auto-generated draft article.
     *
     * Same site-boundary rules as approveGeneratedContent().
     */
    public function rejectGeneratedContent($user, EducationArticle $article): bool
    {
        return $this->approveGeneratedContent($user, $article);
    }
}
