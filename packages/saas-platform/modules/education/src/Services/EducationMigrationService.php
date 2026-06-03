<?php

namespace SaaSPlatform\Modules\Education\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EducationMigrationService
{
    public function migrateKbCategories(): int
    {
        $rows = DB::table('kb_categories')->get();
        $count = 0;

        foreach ($rows as $row) {
            $exists = DB::table('edu_categories')->where('slug', $row->slug)->exists();
            if ($exists) {
                continue;
            }

            DB::table('edu_categories')->insert([
                'parent_id'    => $row->parent_id,
                'site_id'      => $row->site_id,
                'name'         => $row->name,
                'slug'         => $row->slug,
                'icon'         => $row->icon,
                'sort_order'   => $row->sort_order,
                'content_type' => 'articles',
                'visibility'   => 'authenticated',
                'is_active'    => $row->is_active,
                'created_at'   => $row->created_at,
                'updated_at'   => $row->updated_at,
            ]);
            $count++;
        }

        return $count;
    }

    public function migrateKbArticles(): int
    {
        $rows = DB::table('kb_articles')->get();
        $count = 0;

        foreach ($rows as $row) {
            $exists = DB::table('edu_articles')->where('slug', $row->slug)->exists();
            if ($exists) {
                continue;
            }

            // Resolve category: find matching edu_category by the kb_category's slug
            $kbCat = DB::table('kb_categories')->find($row->kb_category_id);
            $eduCat = $kbCat
                ? DB::table('edu_categories')->where('slug', $kbCat->slug)->first()
                : null;

            DB::table('edu_articles')->insert([
                'edu_category_id'   => $eduCat?->id ?? 1,
                'site_id'           => $row->site_id,
                'author_id'         => $row->author_id,
                'title'             => $row->title,
                'slug'              => $row->slug,
                'excerpt'           => $row->excerpt,
                'body'              => $row->body,
                'status'            => $row->status,
                'content_type'      => 'article',
                'view_count'        => $row->view_count,
                'helpful_count'     => $row->helpful_count,
                'not_helpful_count' => $row->not_helpful_count,
                'tags'              => $row->tags,
                'published_at'      => $row->published_at,
                'created_at'        => $row->created_at,
                'updated_at'        => $row->updated_at,
                'deleted_at'        => null,
            ]);
            $count++;
        }

        return $count;
    }

    public function tablesExist(): array
    {
        return [
            'kb_categories' => DB::getSchemaBuilder()->hasTable('kb_categories'),
            'kb_articles'   => DB::getSchemaBuilder()->hasTable('kb_articles'),
            'edu_categories'=> DB::getSchemaBuilder()->hasTable('edu_categories'),
            'edu_articles'  => DB::getSchemaBuilder()->hasTable('edu_articles'),
        ];
    }
}
