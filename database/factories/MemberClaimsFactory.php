<?php

namespace Database\Factories;

use App\Models\MemberClaims;
use App\Models\MemberPlanLink;
use App\Models\Members;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MemberClaims>
 */
class MemberClaimsFactory extends Factory
{
    protected $model = MemberClaims::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $serviceDate = $this->faker->dateTimeBetween('1/1/2026', 'now');
        $freshdesk_claim_id = $this->faker->unique()->numberBetween(1000, 9999);
        $new = [
            'member_id' => null,
            'claim_id' => null,
            'freshdesk_claim_id' => $freshdesk_claim_id,
            'service_date' => $serviceDate,
            'member_plan_links_id' => null
        ];

        return $new;
    }

    public function withSubClaims()
    {
        return $this->afterCreating(function (MemberClaims $claim) {
            $benefit = MemberPlanLink::find($claim->member_plan_links_id)->benefitPeriod->benefit;

            $claim->type = $benefit->type;
            $claim->coverage = $benefit->name;
            $prefix = config('claim.type_abbreviations')[$claim->type] ?? 'CLM';
            $claim->claim_id = $prefix . '' . str_pad($claim->id, 6, '0', STR_PAD_LEFT);
            $claim->version = 'v2';
            $claim->received_date = $this->faker->dateTimeBetween('1/1/2026', 'now');
            $claim->save();

            $total_claim_amount = 0;
            // number random from 2-6
            $numItems = $this->faker->numberBetween(2, 6);
            if ($claim->type == 'fsa') {
                for ($i = 0; $i < $numItems; $i++) {
                    $amount = $this->faker->randomFloat(2, 10, 1000);
                    $total_claim_amount += $amount;
                    $category = $this->faker->randomElement(['Meal', 'Transport']);
                    $serviceDate = $this->faker->dateTimeBetween('1/1/2026', 'now');

                    $subClaim = $claim->subClaimDetails()->create([
                        'amount' => $amount,
                        'purpose' => $category,
                        'vendor_name' => $this->faker->company(),
                        'receipt_date' => $serviceDate,
                    ]);

                    $subClaim->attachments()->createMany(
                        SubClaimDetailAttachmentFactory::new()
                            ->count($this->faker->numberBetween(1, 3))
                            ->make()
                            ->toArray()
                    );
                }
            } else if ($claim->type == 'choicepot') {
                for ($i = 0; $i < $numItems; $i++) {
                    $amount = $this->faker->randomFloat(2, 10, 1000);
                    $total_claim_amount += $amount;
                    $category = DB::table('claim_categories')
                        ->where('claim_type', $claim->type)
                        ->inRandomOrder()
                        ->first();
                    
                    $subCategory = DB::table('claim_subcategories')
                        ->where('category_id', $category->id)
                        ->inRandomOrder()
                        ->first()
                        ->name;
                    
                    $serviceDate = $this->faker->dateTimeBetween('1/1/2026', 'now');

                    $subClaim = $claim->subClaimDetails()->create([
                        'amount' => $amount,
                        'category' => $category->name,
                        'sub_category' => $subCategory,
                        'activities_or_items' => $this->faker->sentence(),
                        'description' => $this->faker->sentence(),
                        'relation_to_employee' => $this->faker->randomElement(['Employee', 'Dependent']),
                        'vendor_name' => $this->faker->company(),
                        'receipt_date' => $serviceDate,
                        'or_number' => $this->faker->bothify('OR-####'),
                    ]);

                    $subClaim->attachments()->createMany(
                        SubClaimDetailAttachmentFactory::new()
                            ->count($this->faker->numberBetween(1, 3))
                            ->make()
                            ->toArray()
                    );
                }
            }

            // Update total claim amount
            $claim->update(['total_amount' => $total_claim_amount]);
        });
    }

    public function userId($userId)
    {
        return $this->state(function (array $attributes) use ($userId) {
            return [
                'member_id' => $userId,
            ];
        });
    }

    public function planLinkId($planLinkId)
    {
        return $this->state(function (array $attributes) use ($planLinkId) {
            return [
                'member_plan_links_id' => $planLinkId,
            ];
        });
    }
}
