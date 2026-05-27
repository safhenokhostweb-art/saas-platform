<?php

namespace SaaSPlatform\CoreUtils\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use SaaSPlatform\CoreUtils\AI\AiRuntimeCoordinator;
use SaaSPlatform\CoreUtils\AI\AiFormBuilderAssistant;
use SaaSPlatform\CoreUtils\AI\AiSupportReplyAssistant;
use SaaSPlatform\CoreUtils\AI\Governance\TenantAiQuotaManager;
use SaaSPlatform\CoreUtils\AI\Governance\AiRateLimitCoordinator;
use SaaSPlatform\CoreUtils\AI\Observability\AiTelemetryService;
use SaaSPlatform\CoreUtils\AI\Safety\AiRuntimeSafetyEngine;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryContext;

class AiApiController extends Controller
{
    public function __construct(
        private readonly AiRuntimeCoordinator    $coordinator,
        private readonly AiFormBuilderAssistant  $formAssistant,
        private readonly AiSupportReplyAssistant $ticketAssistant,
        private readonly TenantAiQuotaManager    $quotaManager,
        private readonly AiRateLimitCoordinator  $rateLimiter,
        private readonly AiTelemetryService      $telemetry,
        private readonly AiRuntimeSafetyEngine   $safetyEngine,
        private readonly TelemetryContext        $telemetryContext,
    ) {}

    // ─── Middleware applied at route level ────────────────────────────────────

    /** POST /api/ai/suggest */
    public function suggest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt'   => 'required|string|max:4000',
            'context'  => 'nullable|string|max:2000',
            'domain'   => 'nullable|string|max:100',
            'max_items' => 'nullable|integer|min:1|max:20',
        ]);

        return $this->executeAiRequest($request, 'suggest', function () use ($validated, $request) {
            $tenantId = $this->resolveTenantId($request);
            $result = $this->coordinator->complete(
                $validated['prompt'],
                $tenantId,
                $validated['domain'] ?? 'general',
                ['max_tokens' => 512, 'context' => $validated['context'] ?? '']
            );
            return ['suggestions' => $result->suggestions ?? [$result->text], 'confidence' => $result->confidence ?? 0.8];
        });
    }

    /** POST /api/ai/chat */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'messages'  => 'required|array|min:1|max:50',
            'messages.*.role'    => 'required|in:user,assistant,system',
            'messages.*.content' => 'required|string|max:8000',
            'domain'    => 'nullable|string|max:100',
            'stream'    => 'nullable|boolean',
        ]);

        return $this->executeAiRequest($request, 'chat', function () use ($validated, $request) {
            $tenantId = $this->resolveTenantId($request);
            $result = $this->coordinator->completeChat(
                $validated['messages'],
                $tenantId,
                $validated['domain'] ?? 'general',
                ['max_tokens' => 1024]
            );
            return ['message' => $result->text, 'confidence' => $result->confidence ?? 0.8, 'provider' => $result->provider ?? 'unknown'];
        });
    }

    /** POST /api/ai/complete */
    public function complete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt'     => 'required|string|max:8000',
            'max_tokens' => 'nullable|integer|min:1|max:4096',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'domain'     => 'nullable|string|max:100',
        ]);

        return $this->executeAiRequest($request, 'complete', function () use ($validated, $request) {
            $tenantId = $this->resolveTenantId($request);
            $result = $this->coordinator->complete(
                $validated['prompt'],
                $tenantId,
                $validated['domain'] ?? 'general',
                [
                    'max_tokens'  => $validated['max_tokens'] ?? 512,
                    'temperature' => $validated['temperature'] ?? 0.7,
                ]
            );
            return ['text' => $result->text, 'tokens_used' => $result->tokensUsed ?? 0];
        });
    }

    /** POST /api/ai/analyze */
    public function analyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content'  => 'required|string|max:16000',
            'analysis_type' => 'nullable|string|in:sentiment,summary,classification,entities,risk',
            'domain'   => 'nullable|string|max:100',
        ]);

        return $this->executeAiRequest($request, 'analyze', function () use ($validated, $request) {
            $tenantId = $this->resolveTenantId($request);
            $type  = $validated['analysis_type'] ?? 'summary';
            $prompt = match($type) {
                'sentiment'      => "Analyze the sentiment of the following text. Return JSON with: sentiment (positive/negative/neutral), score (-1 to 1), reasoning.\n\n{$validated['content']}",
                'classification' => "Classify the following text. Return JSON with: category, subcategory, confidence.\n\n{$validated['content']}",
                'entities'       => "Extract named entities from the following text. Return JSON with: entities array (text, type, confidence).\n\n{$validated['content']}",
                'risk'           => "Analyze the risk level of the following content. Return JSON with: risk_level (low/medium/high/critical), factors array, recommendation.\n\n{$validated['content']}",
                default          => "Summarize the following text concisely.\n\n{$validated['content']}",
            };
            $result = $this->coordinator->complete($prompt, $tenantId, $validated['domain'] ?? 'analysis', ['max_tokens' => 512]);
            return ['analysis_type' => $type, 'result' => $result->text, 'confidence' => $result->confidence ?? 0.8];
        });
    }

    /** POST /api/ai/generate */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'    => 'required|string|in:text,email,template,form,report,summary',
            'context' => 'required|string|max:4000',
            'tone'    => 'nullable|string|in:professional,friendly,formal,casual,technical',
            'length'  => 'nullable|string|in:short,medium,long',
            'domain'  => 'nullable|string|max:100',
        ]);

        return $this->executeAiRequest($request, 'generate', function () use ($validated, $request) {
            $tenantId = $this->resolveTenantId($request);
            $tone   = $validated['tone'] ?? 'professional';
            $length = $validated['length'] ?? 'medium';
            $prompt = "Generate {$validated['type']} content. Tone: {$tone}. Length: {$length}.\n\nContext: {$validated['context']}";
            $result = $this->coordinator->complete($prompt, $tenantId, $validated['domain'] ?? 'generate', ['max_tokens' => 1024]);
            return ['generated' => $result->text, 'type' => $validated['type'], 'tokens_used' => $result->tokensUsed ?? 0];
        });
    }

    /** POST /api/ai/translate */
    public function translate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text'        => 'required|string|max:8000',
            'target_lang' => 'required|string|max:10',
            'source_lang' => 'nullable|string|max:10',
            'preserve_formatting' => 'nullable|boolean',
        ]);

        return $this->executeAiRequest($request, 'translate', function () use ($validated, $request) {
            $tenantId = $this->resolveTenantId($request);
            $src = $validated['source_lang'] ?? 'auto-detect';
            $prompt = "Translate the following text from {$src} to {$validated['target_lang']}. Return ONLY the translated text.\n\n{$validated['text']}";
            $result = $this->coordinator->complete($prompt, $tenantId, 'translate', ['max_tokens' => 2048]);
            return ['translated' => $result->text, 'target_lang' => $validated['target_lang'], 'source_lang' => $src];
        });
    }

    /** POST /api/ai/summarize */
    public function summarize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text'   => 'required|string|max:32000',
            'length' => 'nullable|string|in:brief,standard,detailed',
            'format' => 'nullable|string|in:paragraph,bullets,structured',
            'domain' => 'nullable|string|max:100',
        ]);

        return $this->executeAiRequest($request, 'summarize', function () use ($validated, $request) {
            $tenantId = $this->resolveTenantId($request);
            $length = $validated['length'] ?? 'standard';
            $format = $validated['format'] ?? 'paragraph';
            $prompt = "Summarize the following text. Length: {$length}. Format: {$format}.\n\n{$validated['text']}";
            $result = $this->coordinator->complete($prompt, $tenantId, $validated['domain'] ?? 'summarize', ['max_tokens' => 512]);
            return ['summary' => $result->text, 'length' => $length, 'format' => $format];
        });
    }

    /** POST /api/ai/form-assistant */
    public function formAssistant(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action'  => 'required|string|in:generate,suggest_fields,optimize,analyze_submissions,summarize_submissions',
            'prompt'  => 'nullable|string|max:4000',
            'form_id' => 'nullable|integer',
            'context' => 'nullable|array',
        ]);

        return $this->executeAiRequest($request, 'form-assistant', function () use ($validated, $request) {
            $tenantId = $this->resolveTenantId($request);
            $action   = $validated['action'];
            $result   = match($action) {
                'generate'              => $this->formAssistant->generateFromPrompt($validated['prompt'] ?? '', $tenantId),
                'suggest_fields'        => $this->formAssistant->suggestFields($validated['context'] ?? [], $tenantId),
                'optimize'              => $this->formAssistant->optimizeForm($validated['form_id'] ?? 0, $tenantId),
                'analyze_submissions'   => $this->formAssistant->analyzeSubmissions($validated['form_id'] ?? 0, $tenantId),
                'summarize_submissions' => $this->formAssistant->summarizeSubmissions($validated['form_id'] ?? 0, $tenantId),
                default                 => ['error' => 'Unknown action'],
            };
            return ['action' => $action, 'result' => $result];
        });
    }

    /** POST /api/ai/ticket-assistant */
    public function ticketAssistant(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action'    => 'required|string|in:suggest_reply,rewrite,internal_note,escalation_summary,sentiment,thread_summary,churn_risk',
            'ticket_id' => 'nullable|integer',
            'draft'     => 'nullable|string|max:8000',
            'context'   => 'nullable|array',
        ]);

        return $this->executeAiRequest($request, 'ticket-assistant', function () use ($validated, $request) {
            $tenantId = $this->resolveTenantId($request);
            $action   = $validated['action'];
            $result   = match($action) {
                'suggest_reply'      => $this->ticketAssistant->suggestReply($validated['ticket_id'] ?? 0, $tenantId),
                'rewrite'            => $this->ticketAssistant->rewriteReply($validated['draft'] ?? '', $tenantId),
                'internal_note'      => $this->ticketAssistant->generateInternalNote($validated['ticket_id'] ?? 0, $tenantId),
                'escalation_summary' => $this->ticketAssistant->generateEscalationSummary($validated['ticket_id'] ?? 0, $tenantId),
                'sentiment'          => $this->ticketAssistant->analyzeSentiment($validated['ticket_id'] ?? 0, $tenantId),
                'thread_summary'     => $this->ticketAssistant->summarizeThread($validated['ticket_id'] ?? 0, $tenantId),
                'churn_risk'         => $this->ticketAssistant->assessChurnRisk($validated['ticket_id'] ?? 0, $tenantId),
                default              => ['error' => 'Unknown action'],
            };
            return ['action' => $action, 'result' => $result];
        });
    }

    /** POST /api/ai/automation-assistant */
    public function automationAssistant(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'context'  => 'required|string|max:4000',
            'module'   => 'nullable|string|in:forms,tickets,general',
            'triggers' => 'nullable|array',
        ]);

        return $this->executeAiRequest($request, 'automation-assistant', function () use ($validated, $request) {
            $tenantId = $this->resolveTenantId($request);
            $prompt   = "Suggest automation rules for the following context:\n\n{$validated['context']}\n\nModule: ".($validated['module'] ?? 'general')."\n\nReturn JSON array of automation suggestions with: name, trigger, condition, action, description.";
            $result   = $this->coordinator->complete($prompt, $tenantId, 'automation', ['max_tokens' => 1024]);
            return ['suggestions' => $result->text, 'module' => $validated['module'] ?? 'general'];
        });
    }

    // ─── Internals ───────────────────────────────────────────────────────────

    private function executeAiRequest(Request $request, string $operation, callable $handler): JsonResponse
    {
        $correlationId = $request->header('X-Correlation-Id', Str::uuid()->toString());
        $tenantId      = $this->resolveTenantId($request);
        $start         = microtime(true);

        try {
            // Safety gate
            $safety = $this->safetyEngine->inspectRequest($request->all(), $tenantId, $operation);
            if (! $safety['allowed']) {
                return $this->errorResponse('AI request blocked by safety engine', 422, [
                    'reason'      => $safety['reason'] ?? 'safety_violation',
                    'correlation_id' => $correlationId,
                ]);
            }

            // Quota check
            if ($tenantId) {
                $quota = $this->quotaManager->check($tenantId);
                if (! $quota['allowed']) {
                    return $this->errorResponse('AI quota exceeded', 429, [
                        'quota_reset_at' => $quota['reset_at'] ?? null,
                        'correlation_id' => $correlationId,
                    ]);
                }
            }

            // Rate limit
            $rateKey = "ai:{$operation}:{$tenantId}:".($request->user()?->id ?? 'guest');
            if (! $this->rateLimiter->attempt($rateKey, 30, 60)) {
                return $this->errorResponse('Too many AI requests', 429, [
                    'retry_after' => 60,
                    'correlation_id' => $correlationId,
                ]);
            }

            $result = $handler();

            $this->telemetry->record($operation, $tenantId, [
                'duration_ms'    => round((microtime(true) - $start) * 1000),
                'correlation_id' => $correlationId,
                'success'        => true,
            ]);

            return response()->json([
                'success'        => true,
                'data'           => $result,
                'correlation_id' => $correlationId,
                'operation'      => $operation,
                'latency_ms'     => round((microtime(true) - $start) * 1000),
            ])->header('X-Correlation-Id', $correlationId);

        } catch (\Throwable $e) {
            $this->telemetry->record($operation, $tenantId, [
                'duration_ms'    => round((microtime(true) - $start) * 1000),
                'correlation_id' => $correlationId,
                'success'        => false,
                'error'          => $e->getMessage(),
            ]);

            return $this->errorResponse(
                config('app.debug') ? $e->getMessage() : 'AI service temporarily unavailable',
                $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 503,
                ['correlation_id' => $correlationId, 'fallback' => true]
            );
        }
    }

    private function resolveTenantId(Request $request): string
    {
        if ($request->attributes->has('tenant_site')) {
            $site = $request->attributes->get('tenant_site');
            return (string) ($site->id ?? '');
        }
        return (string) ($request->user()?->site_id ?? session('site_id', ''));
    }

    private function errorResponse(string $message, int $status, array $meta = []): JsonResponse
    {
        return response()->json(array_merge([
            'success' => false,
            'message' => $message,
        ], $meta), min($status, 503));
    }
}
