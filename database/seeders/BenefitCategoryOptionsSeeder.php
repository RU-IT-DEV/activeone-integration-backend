<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class BenefitCategoryOptionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $data = [
            #uflex
            [
                'name' => 'Groceries',
                'type' => 'uflex',
                'description' => "food for human consumption, e.g. fresh, frozen, and processed produce, pet food\n➢ non-food consumables for personal and household use, e.g. toiletries, detergent/cleaning aid, etc.\n➢ single big ticket items are not allowed:\n➔ appliances, e.g. oven toaster, rice cooker, air fryer, microwave oven, fan, vacuum cleaner, etc.\n➔ white goods, e.g. washer/dryer, refrigerator/freezer, air conditioner, television, stovetop/oven, etc.",
            ],
            [
                'name' => 'Prescription lenses',
                'type' => 'uflex',
                'description' => "including eyeglasses and/or contact lenses\n➢ eye care, e.g. eye drops\n➢ contact lens consumables, including contact lens tablets and solution\n➢ light or radiation eye protection, e.g. sun/dark glasses or blue light lenses - charges beyond employee benefit limits and plan coverage",
            ],
            [
                'name' => 'Medicine Reimbursement',
                'type' => 'uflex',
                'description' => "➢ Unilab products purchased outside\n➢ MedVale purchases which are not eligible for medicine reimbursement, e.g. vitamins, personal care and OTC products, etc.",
            ],
            [
                'name' => 'Health Insurance Premium',
                'type' => 'uflex',
                'description' => "2nd Layer Hospitalization Plan for Employees and Dependents (2 nd Layer)\n➢ Voluntary Extended Health Insurance (VEHI)",
            ],
            [
                'name' => 'Treatment/Procedure',
                'type' => 'uflex',
                'description' => "warts removal\n➢ dental\n➢ physical therapy",
            ],
            # core
            [
                'name' => 'Rice',
                'type' => 'core',
                'description' => "",
            ],
            [
                'name' => 'Education',
                'type' => 'core',
                'description' => "",
            ],
            [
                'name' => 'Optical',
                'type' => 'core',
                'description' => "",
            ],
        ];

        DB::table('benefit_category_options')->insert($data);
    }
}
