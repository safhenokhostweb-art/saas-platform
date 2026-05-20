<?php
namespace App\Services\Seo;

class SchemaBuilder
{
    public static function for($model): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $model->title ?? '',
            'description' => strip_tags($model->description ?? ''),
            'url' => url()->current(),
        ];
    }
}
