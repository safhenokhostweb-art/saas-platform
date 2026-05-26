<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Queue;

use Illuminate\Support\Facades\DB;

class QueueRuntimeBalancer
{
    private const IMBALANCE_RATIO = 3.0; // One queue has 3x more jobs than average = imbalance

    public function analyze(): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('jobs')) {
                return ['available' => false, 'balanced' => true];
            }

            $depths = DB::table('jobs')
                ->selectRaw('queue, count(*) as depth')
                ->groupBy('queue')
                ->pluck('depth', 'queue')
                ->toArray();

            if (empty($depths)) {
                return ['available' => true, 'balanced' => true, 'queues' => []];
            }

            $avg           = array_sum($depths) / count($depths);
            $imbalanced    = [];

            foreach ($depths as $queue => $depth) {
                if ($avg > 0 && ($depth / $avg) >= self::IMBALANCE_RATIO) {
                    $imbalanced[$queue] = ['depth' => $depth, 'ratio' => round($depth / $avg, 2)];
                }
            }

            return [
                'available'  => true,
                'balanced'   => empty($imbalanced),
                'queues'     => $depths,
                'average'    => round($avg, 1),
                'imbalanced' => $imbalanced,
            ];
        } catch (\Throwable) {
            return ['available' => false, 'balanced' => true];
        }
    }

    public function recommendations(array $analysis): array
    {
        if ($analysis['balanced'] ?? true) {
            return [];
        }

        $recs = [];
        foreach ($analysis['imbalanced'] ?? [] as $queue => $info) {
            $recs[] = "Queue '{$queue}' has {$info['depth']} jobs ({$info['ratio']}x average) — consider adding more workers for this queue";
        }

        return $recs;
    }
}
