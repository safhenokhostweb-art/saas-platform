AI Cost Management Rule — Laravel SaaS (DeepSeek V4 Verified)
Purpose
This document outlines the official rules for managing the cost of using DeepSeek AI models within Laravel SaaS projects.
✅ Architecture Overview
Config → Migration → Models → Services → Middleware → Jobs → Controlle

1. Pricing (DeepSeek V4 — April 2026)
   Model Input MissInput Hit Output
   V4-Flash $0.14 / 1M $0.028 / 1M $0.28 / 1M
   V4-Pro $1.74 / 1M $0.145 / 1M $3.48 / 1M
   Cache-hit applies only to the input.
2. Config → config/ai.php
   return [

'models' => [

'flash' => [

'input_miss' => 0.14 / 1000000,

'input_hit' => 0.028 / 1000000,

'output' => 0.28 / 1000000,

],

'pro' => [

'input_miss' => 1.74 / 1000000,

'input_hit' => 0.145 / 1000000,

'output' => 3.48 / 1000000,

],

],

'min_cached_tokens' => 1024,

'local_use' => env('AI_USE_LOCAL', false),

'daily_limit' => env('AI_DAILY_LIMIT', 10.00),

'monthly_limit' => env('AI_MONTHLY_LIMIT', 100.00),

]; 3. Token Estimation → app/Services/
TokenEstimator.php

public function estimate(string $text): int {

$isPersian = preg_match('/[\x{0600}-\x{06FF}]/u', $text);

$ratio = $isPersian ? 2.5 : 4;

return ceil(strlen($text) / $ratio);

} 4. Cache (Prefix Reuse)
• No TTL
• If prefix ≥ 1024 tokens → cache eligible
• Cost of cached tokens is calculated based on the input_hit rate. 5. Logging Rules
AiUsageLog is created only when: - The API response is successful (HTTP 200). - The number of tokens is received. - The cost is calculated according to the prices above.

No cost logs are generated for errors. 6. Rollback Policy
Removed. In case of failure, no log or rollback is recorded. 7. Fallback Policy
• Only active when APP_ENV=local and AI_USE_LOCAL=true.
• No fallback in Production. 8. Queue Job Behavior (LogAiUsage.php)
• In Production: Batch insert is enabled.
• In Local: Single insert is used. 9. Middleware (EnsureUserHasAiCredits.php)
• Checks user and tenant budget.
• Returns HTTP 429 if over budget. 10. Controller Workflow (AiController.php)

1. Estimate Cost
2. Validate Budget
3. Route Model
4. Call Provider
5. Log on Success
6. Return Response (+ cost_usd, model_used, cache_hit)
   Timeout: 30 seconds

7. Database Schema (simplified)
   Table: ai_usage_logs | Field | Type | Notes | |----------------|--------------|----------------| | tenant_id | bigint | SaaS tenant | | user_id | bigint | Requester | | model_used | string | flash / pro | | task_type | string | logic / code / text | | prompt_tokens | int | Input tokens | | completion_tokens | int | Output tokens | | cached_tokens | int | Cached prefix | | cost_input_miss | decimal(10,5) | Cost without cache | |
   cost_input_hit | decimal(10,5) | Cost of cached input | | cost_output |
   decimal(10,5) | Cost of output | | total_cost | decimal(10,5) | Total combined | | cache_hit | boolean | true / false | | status | string | success / error | | created_at | datetime | |
8. Environment Variables (.env)
   AI_USE_LOCAL=true

AI_DAILY_LIMIT=10.00

AI_MONTHLY_LIMIT=100.00

DEEPSEEK_API_KEY=your_key_here

DEEPSEEK_V4_FLASH_MODEL=deepseek-v4-flash

DEEPSEEK_V4_PRO_MODEL=deepseek-v4-pro

APP_ENV=local

OLLAMA_URL=http://localhost:11434 13. Enforcement
• ❌ No TTL
• ✅ Token ratio 2.5 (fa) / 4 (en)
• ❌ No rollback
• ✅ Log only on success
• ✅ Fallback only local
• ✅ DeepSeek verified pricing
✅ Status: Approved for Production use Place this file at rules/
ai_cost_management.rule.md
