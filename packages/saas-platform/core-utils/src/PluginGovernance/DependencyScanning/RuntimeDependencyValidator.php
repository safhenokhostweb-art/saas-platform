<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\DependencyScanning;

class RuntimeDependencyValidator
{
    public function __construct(
        private UnsafeImportDetector       $importDetector,
        private SandboxCompatibilityAnalyzer $compatibilityAnalyzer,
        private DependencyRiskClassifier   $classifier
    ) {}

    public function validateFile(string $filePath): array
    {
        $findings       = $this->importDetector->scanFile($filePath);
        $compatibility  = $this->compatibilityAnalyzer->analyze(file_exists($filePath) ? file_get_contents($filePath) : '');
        $classification = $this->classifier->classify($findings);

        return [
            'file'            => $filePath,
            'classification'  => $classification,
            'findings'        => $findings,
            'compatibility'   => $compatibility,
            'execution_allowed' => $this->classifier->isExecutionAllowed($classification),
            'sandboxable'     => $this->classifier->isSandboxable($classification),
        ];
    }

    public function validateSource(string $sourceCode, string $label = 'inline'): array
    {
        $findings       = $this->importDetector->scan($sourceCode);
        $compatibility  = $this->compatibilityAnalyzer->analyze($sourceCode);
        $classification = $this->classifier->classify($findings);

        return [
            'label'           => $label,
            'classification'  => $classification,
            'findings'        => $findings,
            'compatibility'   => $compatibility,
            'execution_allowed' => $this->classifier->isExecutionAllowed($classification),
            'sandboxable'     => $this->classifier->isSandboxable($classification),
        ];
    }

    public function validateDirectory(string $path): array
    {
        if (!is_dir($path)) {
            return ['error' => 'Directory not found', 'path' => $path];
        }

        $results = [];
        $files   = glob($path . '/**/*.php', GLOB_BRACE) ?: [];

        foreach ($files as $file) {
            $result = $this->validateFile($file);

            if ($result['classification'] !== DependencyRiskClassifier::SAFE) {
                $results[] = $result;
            }
        }

        $overall = DependencyRiskClassifier::SAFE;
        foreach ($results as $r) {
            if (!$this->classifier->isExecutionAllowed($r['classification'])) {
                $overall = $r['classification'];
                break;
            }
        }

        return [
            'path'           => $path,
            'scanned_files'  => count($files),
            'issues_found'   => count($results),
            'overall_classification' => $overall,
            'issues'         => $results,
        ];
    }
}
