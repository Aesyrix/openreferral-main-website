<?php

namespace Database\Factories;

use App\Enums\LeadIntent;
use App\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lead> */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        $min = $this->faker->numberBetween(150, 700) * 1000;

        return [
            'name' => $this->faker->name(),
            'phone' => $this->faker->numerify('##########'),
            'email' => $this->faker->unique()->safeEmail(),
            'intent' => $this->faker->randomElement(LeadIntent::cases()),
            'property_type' => $this->faker->randomElement(['Single Family Home', 'Condo', 'Townhouse']),
            'price_min' => $min,
            'price_max' => $min + 100000,
            'location' => $this->faker->city().', FL',
            'timeline' => $this->faker->randomElement(['ASAP', 'Within 30 days', '6+ months']),
            'bedrooms' => (string) $this->faker->numberBetween(1, 5),
            'bathrooms' => (string) $this->faker->numberBetween(1, 4),
            'source' => 'Website',
            'notes' => $this->faker->sentence(),
            'lead_fee_cents' => 25000,
            'status' => LeadStatus::NEW,
        ];
    }

    public function assignedTo(int $agentId, ?int $staffId = null): static
    {
        return $this->state(fn () => [
            'assigned_to' => $agentId,
            'assigned_by' => $staffId,
            'assigned_at' => now()->subHour(),
            'status' => LeadStatus::NEW,
        ]);
    }

    public function unassigned(): static
    {
        return $this->state(fn () => [
            'assigned_to' => null,
            'assigned_by' => null,
            'assigned_at' => null,
        ]);
    }
}
