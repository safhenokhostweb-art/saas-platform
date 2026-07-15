<?php

namespace SaaSPlatform\Modules\MotionGraphics\Services;

use Illuminate\Validation\ValidationException;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionAssetPipelineContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionAssetStorageContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionCompilerContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionOptimizerContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionPreviewGeneratorContract;
use Throwable;

final class MotionAssetPipeline implements MotionAssetPipelineContract
{
    public function __construct(
        private readonly MotionCompilerContract $compiler,
        private readonly MotionOptimizerContract $optimizer,
        private readonly MotionPreviewGeneratorContract $previews,
        private readonly MotionAssetStorageContract $storage,
    ) {}

    public function prepare(array $input, string $slug, int $version): array
    {
        $engine = strtolower((string) ($input['engine'] ?? ''));
        $type = strtolower((string) ($input['asset_type'] ?? ''));
        if (! in_array($engine, config('motion-graphics.allowed_engines', []), true)
            || ! in_array($type, config('motion-graphics.allowed_asset_types', []), true)) {
            throw ValidationException::withMessages(['engine' => 'Unsupported motion engine or asset type.']);
        }
        $source = $input['source_definition'] ?? null;
        if (! is_array($source) || $source === []) {
            throw ValidationException::withMessages(['source_definition' => 'A complete normalized source definition is required.']);
        }
        $encoded = json_encode($source, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if (strlen($encoded) > (int) config('motion-graphics.limits.max_source_bytes', 2097152)) {
            throw ValidationException::withMessages(['source_definition' => 'Source definition exceeds the configured limit.']);
        }
        $this->validateFormat($type, $source, $input['files'] ?? []);
        $this->validateDependencies($input['dependencies'] ?? []);
        $prefix = trim((string) config('motion-graphics.asset_path_prefix', 'motion'), '/').'/'.$slug.'/v'.$version;

        try {
            $files = $this->optimizer->optimize($this->compiler->compile([
                ...$input, 'engine' => $engine, 'asset_type' => $type, 'source_definition' => $source,
            ]));
            $assets = [];
            foreach ($files as $file) {
                $relative = $this->safeRelativePath((string) ($file['relative_path'] ?? ''));
                $contents = $file['contents'] ?? null;
                $mime = (string) ($file['mime'] ?? '');
                if (! is_string($contents) || $mime === '') {
                    throw ValidationException::withMessages(['files' => 'Compiled files require contents and MIME type.']);
                }
                if (strlen($contents) > (int) config('motion-graphics.limits.max_asset_bytes', 5242880)) {
                    throw ValidationException::withMessages(['files' => 'Compiled asset exceeds the configured limit.']);
                }
                $this->validateExecutableContent($contents, $mime);
                $assets[] = $this->storage->put($prefix.'/'.$relative, $contents, $mime);
            }
            if ($assets === []) {
                throw ValidationException::withMessages(['files' => 'Compilation produced no assets.']);
            }
            return [
                'engine' => $engine,
                'asset_type' => $type,
                'source_definition' => $source,
                'compiled_assets' => $assets,
                'preview_metadata' => $this->previews->generate($source, $assets, $prefix),
                'source_checksum' => hash('sha256', $encoded),
            ];
        } catch (Throwable $exception) {
            $this->storage->deletePrefix($prefix);
            throw $exception;
        }
    }

    private function safeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || str_starts_with($path, '/') || preg_match('/(^|\/)\.\.?($|\/)/', $path)
            || preg_match('/^[a-z]:/i', $path)) {
            throw ValidationException::withMessages(['files' => 'Unsafe asset path.']);
        }
        return $path;
    }

    private function validateDependencies(array $dependencies): void
    {
        if (config('motion-graphics.allow_remote_dependencies', false)) {
            return;
        }
        foreach ($dependencies as $dependency) {
            if (preg_match('#^(https?:)?//#i', (string) $dependency)) {
                throw ValidationException::withMessages(['dependencies' => 'Remote dependencies are disabled.']);
            }
        }
    }

    private function validateFormat(string $type, array $source, array $files): void
    {
        if ($type === 'lottie' && (! isset($source['v'], $source['fr'], $source['layers']) || ! is_array($source['layers']))) {
            throw ValidationException::withMessages(['source_definition' => 'Malformed Lottie definition.']);
        }
        if ($type === 'svg') {
            $record = collect($files)->firstWhere('mime', 'image/svg+xml');
            $svg = is_array($record) ? ($record['contents'] ?? '') : '';
            if (! is_string($svg) || ! preg_match('/<svg\b/i', $svg)) {
                throw ValidationException::withMessages(['files' => 'Malformed SVG source.']);
            }
        }
    }

    private function validateExecutableContent(string $contents, string $mime): void
    {
        if ($mime === 'image/svg+xml' && preg_match('/<script\b|\son\w+\s*=|<foreignObject\b|(?:href|src)\s*=\s*[\'"]https?:/i', $contents)) {
            throw ValidationException::withMessages(['files' => 'Unsafe executable SVG content.']);
        }
        if (in_array($mime, ['text/html', 'application/javascript', 'text/javascript'], true)
            && preg_match('/\beval\s*\(|document\.cookie|(?:src|href)\s*=\s*[\'"]https?:/i', $contents)) {
            throw ValidationException::withMessages(['files' => 'Unsafe executable content.']);
        }
    }
}
