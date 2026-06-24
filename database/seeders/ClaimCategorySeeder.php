<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ClaimCategory;
use App\Models\ClaimSubCategory;

class ClaimCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            'choicepot' => [
                'Family Care' => ['Growth'],
                'Personal Growth' => ['Health'],
                'Physical and Mental Welness' => ['Personal Development'],
                'Sustainability' => ['Professional Development', 'Health', 'Personal Fitness', 'Vaacation', 'Green Benefit'],
            ],
            'fsa' => [
                'Meal' => [],
                'Transportation' => []
            ],
            'reimbursement' => [
                'uflex' => ['Groceries', 'Prescription lenses', 'Medicine Reimbursement', 'Health Insurance Premium', 'Treatment/Procedure'],
                'Rice' => [],
                'Education Aid' => [],
                'Optical' => [],
            ],
        ];

        foreach ($data as $claimType => $categories) {
            foreach ($categories as $categoryName => $subcategories) {
                // Check if category already exists
                $category = ClaimCategory::firstOrCreate([
                    'claim_type' => $claimType,
                    'name' => $categoryName,
                ]);

                foreach ($subcategories as $subName) {
                    // Only insert if subcategory doesn't already exist
                    ClaimSubcategory::firstOrCreate([
                        'category_id' => $category->id,
                        'name' => $subName,
                    ]);
                }
            }
        }
    }
}
