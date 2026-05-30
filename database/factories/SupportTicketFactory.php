<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SupportTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => 1, // باید در تست site_id واقعی داده شود
            'subject' => fake()->sentence(),
            'message' => fake()->paragraph(),
            'status' => SupportTicket::STATUS_OPEN,
            'priority' => SupportTicket::PRIORITY_NORMAL,
            'support_level' => 'basic',
            'customer_mobile' => '09' . fake()->numerify('#########'),
            'customer_name' => fake()->name(),
        ];
    }

    /**
     * تیکت در وضعیت in_progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => SupportTicket::STATUS_IN_PROGRESS,
            'first_response_at' => now(),
        ]);
    }

    /**
     * تیکت بسته شده.
     */
    public function closed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => SupportTicket::STATUS_CLOSED,
            'closed_at' => now(),
            'resolved_at' => now(),
        ]);
    }

    /**
     * تیکت با اولویت بالا.
     */
    public function highPriority(): static
    {
        return $this->state(fn(array $attributes) => [
            'priority' => SupportTicket::PRIORITY_HIGH,
        ]);
    }

    /**
     * تیکت با support_level=pro.
     */
    public function proLevel(): static
    {
        return $this->state(fn(array $attributes) => [
            'support_level' => 'pro',
        ]);
    }
}
