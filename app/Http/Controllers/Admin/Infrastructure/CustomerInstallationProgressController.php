<?php

namespace App\Http\Controllers\Admin\Infrastructure;

use App\Http\Controllers\Controller;
use App\Models\CustomerInstallation;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerInstallationProgressController extends Controller
{
    /**
     * Server-Sent Events stream for real-time installation progress.
     * Reads from Redis RPUSH list install_progress_{id}.
     * Falls back to DB polling if Redis unavailable.
     */
    public function stream(CustomerInstallation $installation): StreamedResponse
    {
        return response()->stream(function () use ($installation) {
            $key    = "install_progress_{$installation->id}";
            $cursor = 0;
            $ticks  = 0;
            $maxTicks = 3600; // 1 hour max

            // Send initial state from DB
            $this->sendEvent('init', [
                'id'            => $installation->id,
                'status'        => $installation->status,
                'current_stage' => $installation->current_stage,
                'company'       => $installation->customer_company,
                'domain'        => $installation->customer_domain,
            ]);

            while ($ticks < $maxTicks) {
                if (connection_aborted()) {
                    break;
                }

                // Try Redis first
                $events = $this->readFromRedis($key, $cursor);
                if ($events !== null) {
                    foreach ($events['items'] as $item) {
                        $data = json_decode($item, true);
                        if ($data) {
                            $this->sendEvent('progress', $data);
                        }
                    }
                    $cursor = $events['cursor'];
                }

                // Always send a heartbeat DB status check
                if ($ticks % 5 === 0) {
                    $installation->refresh();
                    $this->sendEvent('status', [
                        'status'        => $installation->status,
                        'current_stage' => $installation->current_stage,
                        'completed_stages' => count($installation->completedStagesList()),
                    ]);

                    // Stop streaming if terminal state
                    if (in_array($installation->status, ['complete', 'rolled_back', 'rollback_failed'])) {
                        $this->sendEvent('done', ['status' => $installation->status]);
                        break;
                    }
                    // Also stop if failed and no active rollback
                    if ($installation->status === 'failed') {
                        $this->sendEvent('done', ['status' => 'failed']);
                        break;
                    }
                }

                ob_flush();
                flush();
                usleep(1_000_000); // 1 second
                $ticks++;
            }

            $this->sendEvent('end', ['message' => 'stream closed']);
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    private function readFromRedis(string $key, int $cursor): ?array
    {
        try {
            $items = Redis::lrange($key, $cursor, -1);
            return [
                'items'  => $items,
                'cursor' => $cursor + count($items),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function sendEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    }
}
