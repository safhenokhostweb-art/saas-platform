<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AI\ModelRouter;
use App\Services\AI\RuleClassifier;
use App\Services\AI\RuleNameGenerator;
use App\Services\AI\RuleWriter;

class GenerateAIRule extends Command
{
    protected $signature = 'ai:rule {prompt}';

    protected $description = 'Generate an engineering rule using AI and save it into the rules directory';

    protected ModelRouter $router;
    protected RuleClassifier $classifier;
    protected RuleNameGenerator $nameGenerator;
    protected RuleWriter $writer;

    public function __construct(
        ModelRouter $router,
        RuleClassifier $classifier,
        RuleNameGenerator $nameGenerator,
        RuleWriter $writer
    ) {
        parent::__construct();

        $this->router = $router;
        $this->classifier = $classifier;
        $this->nameGenerator = $nameGenerator;
        $this->writer = $writer;
    }

    public function handle(): int
    {
        $prompt = $this->argument('prompt');

        $this->info("Generating rule using AI...");
        $this->line("Prompt: {$prompt}");
        $this->newLine();

        try {
            // 1️⃣ Ask AI to generate the rule
            $ruleContent = $this->router->ask(
                "Generate a production-grade engineering RULE in markdown format.\n\n" .
                    "Rule must:\n" .
                    "- Be enforceable\n" .
                    "- Be clear and structured\n" .
                    "- Contain sections\n" .
                    "- Avoid unnecessary verbosity\n\n" .
                    "Topic:\n" . $prompt
            );

            if (empty($ruleContent)) {
                $this->error("AI returned empty response.");
                return self::FAILURE;
            }

            // 2️⃣ Classify rule
            $category = $this->classifier->classify($ruleContent);

            // 3️⃣ Generate semantic filename
            $filename = $this->nameGenerator->generate($prompt);

            // 4️⃣ Prevent duplicate overwrite
            $existingPath = base_path("../rules/{$category}/{$filename}");

            if (file_exists($existingPath)) {
                $this->warn("Rule already exists: {$existingPath}");
                $this->warn("Skipping overwrite to prevent data loss.");
                return self::SUCCESS;
            }

            // 5️⃣ Write rule file
            $path = $this->writer->write($ruleContent, $category, $filename);

            $this->newLine();
            $this->info("✅ Rule successfully created.");
            $this->line("Category : {$category}");
            $this->line("Filename : {$filename}");
            $this->line("Path     : {$path}");

            return self::SUCCESS;
        } catch (\Throwable $e) {

            $this->error("Rule generation failed.");
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
