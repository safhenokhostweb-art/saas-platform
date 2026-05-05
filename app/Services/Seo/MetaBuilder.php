<?php

namespace App\Services\Seo;

class MetaBuilder
{
    public static function og($model): array
    {
        return [
            'title' => $model->title ?? config('app.name'),
            'description' => strip_tags($model->description ?? ''),
            'type' => 'website',
            'url' => url()->current(),
        ];
    }

    public static function twitter($model): array
    {
        return [
            'card' => 'summary_large_image',
            'title' => $model->title ?? config('app.name'),
            'description' => strip_tags($model->description ?? ''),
        ];
    }
}
