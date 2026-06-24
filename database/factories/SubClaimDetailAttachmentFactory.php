<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class SubClaimDetailAttachmentFactory extends Factory
{
    protected $model = \App\Models\SubClaimDetailAttachments::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $randomString = Str::random(16);
        return [
            'filepath' => 'receipts/' . $randomString . '.jpg',
        ];
    }
}
