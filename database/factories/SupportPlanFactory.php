<?php

namespace Database\Factories;

use App\Models\SupportPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * TEST-HARNESS-DB-01: SupportPlan had no factory — the sole caller
 * (ScanRuntimeAnomaliesCommandTest) was masked behind other, earlier
 * failures in the full-suite run until those were fixed.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SupportPlan>
 */
class SupportPlanFactory extends Factory
{
    protected $model = SupportPlan::class;

    public function definition(): array
    {
        return [
            'title' => 'پلن ' . fake()->unique()->word(),
            'price' => fake()->numberBetween(0, 5000000),
            'duration_days' => 30,
            'has_ticket' => true,
            'has_sms' => false,
        ];
    }
}
