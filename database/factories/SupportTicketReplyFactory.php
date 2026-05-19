<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SupportTicketReply>
 */
class SupportTicketReplyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'support_ticket_id' => SupportTicket::factory(),
            'user_id' => User::factory(),
            'message' => fake()->paragraph(),
            'author_type' => SupportTicketReply::AUTHOR_STAFF,
        ];
    }

    /**
     * پاسخ از طرف مشتری.
     */
    public function fromCustomer(): static
    {
        return $this->state(fn(array $attributes) => [
            'author_type' => SupportTicketReply::AUTHOR_CUSTOMER,
            'user_id' => null,
            'customer_email' => fake()->safeEmail(),
            'customer_name' => fake()->name(),
        ]);
    }
}
