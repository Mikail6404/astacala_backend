<?php

namespace Database\Factories;

use App\Models\DisasterReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DisasterReport>
 */
class DisasterReportFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DisasterReport::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $disasterTypes = ['earthquake', 'flood', 'fire', 'hurricane', 'tsunami', 'landslide', 'volcano', 'drought'];
        $severityLevels = ['low', 'medium', 'high', 'critical'];
        $statuses = ['PENDING', 'VERIFIED', 'RESOLVED'];

        return [
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(3),
            'disaster_type' => $this->faker->randomElement($disasterTypes),
            'severity_level' => $this->faker->randomElement($severityLevels),
            'latitude' => $this->faker->latitude(-10, 5), // Indonesia latitude range
            'longitude' => $this->faker->longitude(95, 141), // Indonesia longitude range
            'location_name' => $this->faker->city(),
            'address' => $this->faker->address(),
            'estimated_affected' => $this->faker->numberBetween(1, 1000),
            'weather_condition' => $this->faker->randomElement(['Clear', 'Cloudy', 'Rainy', 'Stormy', 'Windy']),
            'incident_timestamp' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'status' => $this->faker->randomElement($statuses),
            'reported_by' => User::factory(),
            'verified_by_admin_id' => null,
            'verification_notes' => null,
            'metadata' => [
                'source_platform' => $this->faker->randomElement(['mobile', 'web']),
                'submission_method' => $this->faker->randomElement(['app', 'web_dashboard']),
                'device_info' => [
                    'model' => $this->faker->randomElement(['iPhone 13', 'Samsung Galaxy S21', 'Google Pixel 6']),
                    'os' => $this->faker->randomElement(['iOS', 'Android']),
                    'os_version' => $this->faker->randomElement(['15.6', '12.0', '13.0']),
                ],
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the disaster report is pending verification.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'PENDING',
            'verified_by_admin_id' => null,
            'verification_notes' => null,
        ]);
    }

    /**
     * Indicate that the disaster report is verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'VERIFIED',
            'verified_by_admin_id' => User::factory(),
            'verification_notes' => $this->faker->sentence(),
        ]);
    }

    /**
     * Indicate that the disaster report is resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'RESOLVED',
            'verified_by_admin_id' => User::factory(),
            'verification_notes' => $this->faker->paragraph(),
        ]);
    }

    /**
     * Create a mobile-originated report.
     */
    public function fromMobile(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => [
                'source_platform' => 'mobile',
                'submission_method' => 'mobile_app',
                'app_version' => '1.3.0',
                'device_info' => [
                    'model' => $this->faker->randomElement(['iPhone 13', 'Samsung Galaxy S21', 'Google Pixel 6']),
                    'os' => $this->faker->randomElement(['iOS', 'Android']),
                    'os_version' => $this->faker->randomElement(['15.6', '12.0', '13.0']),
                ],
                'location_accuracy' => $this->faker->randomFloat(2, 1, 10),
                'network_type' => $this->faker->randomElement(['wifi', 'cellular']),
            ],
        ]);
    }

    /**
     * Create a web-originated report.
     */
    public function fromWeb(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => [
                'source_platform' => 'web',
                'submission_method' => 'web_dashboard',
                'team_name' => $this->faker->company(),
                'personnel_count' => $this->faker->numberBetween(5, 50),
                'contact_phone' => $this->faker->phoneNumber(),
                'admin_notes' => $this->faker->sentence(),
            ],
        ]);
    }

    /**
     * Create a high-severity report.
     */
    public function highSeverity(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity_level' => 'high',
            'estimated_affected' => $this->faker->numberBetween(100, 1000),
            'disaster_type' => $this->faker->randomElement(['earthquake', 'tsunami', 'volcano', 'hurricane']),
        ]);
    }

    /**
     * Create a critical-severity report.
     */
    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity_level' => 'critical',
            'estimated_affected' => $this->faker->numberBetween(500, 5000),
            'disaster_type' => $this->faker->randomElement(['earthquake', 'tsunami', 'volcano']),
        ]);
    }
}
