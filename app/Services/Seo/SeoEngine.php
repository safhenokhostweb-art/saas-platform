<?php

namespace App\Services\Seo;

use Illuminate\Support\Str;

class SeoEngine
{
    public function generate($model)
    {
        return $model->seoMeta()->updateOrCreate([], [
            'title' => $this->title($model),
            'description' => $this->description($model),
            'canonical' => url()->current(),
            'og' => MetaBuilder::og($model),
            'twitter' => MetaBuilder::twitter($model),
            'schema' => SchemaBuilder::for($model),
            'noindex' => false,
        ]);
    }

    protected function title($model): string
    {
        return ($model->title ?? config('app.name')) . ' | ' . config('app.name');
    }

    protected function description($model): string
    {
        return Str::limit(strip_tags($model->description ?? ''), 160);
    }
}
