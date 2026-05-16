<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\File;

class RuleWriter
{
    public function write(string $category, string $filename, string $content): string
    {
        // fallback category
        if (empty($category)) {
            $category = 'general';
        }

        // base rules directory
        $basePath = base_path('rules');

        // ensure base rules folder exists
        if (!File::exists($basePath)) {
            File::makeDirectory($basePath, 0755, true);
        }

        // category folder
        $categoryPath = $basePath . DIRECTORY_SEPARATOR . $category;

        if (!File::exists($categoryPath)) {
            File::makeDirectory($categoryPath, 0755, true);
        }

        // ensure extension
        if (!str_ends_with($filename, '.rule.md')) {
            $filename .= '.rule.md';
        }

        $path = $categoryPath . DIRECTORY_SEPARATOR . $filename;

        File::put($path, $content);

        return $path;
    }
}
