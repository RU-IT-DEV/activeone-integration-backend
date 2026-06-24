<?php

namespace App\Rules;

use Closure;
use App\Models\BenefitPeriod;
use Illuminate\Contracts\Validation\ValidationRule;
use Carbon\Carbon;

class BenefitPeriodRenewalStartEnd implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // get benefit ID from request
        $benefitId = request()->input('benefit_id');

        // get current benefit period
        $currentBenefitPeriod = BenefitPeriod::where('benefit_id', $benefitId)
            ->where('is_current', true)
            ->first();

        if (!$currentBenefitPeriod) {
            $currentBenefitPeriod = BenefitPeriod::where('benefit_id', $benefitId)
                ->orderBy('id', 'desc')
                ->first();

            if (!$currentBenefitPeriod) {
                $fail('The selected benefit period is invalid.');
                return;
            }
        }

        $value_start = Carbon::parse($value['start']);
        $compare_end = Carbon::parse($currentBenefitPeriod->expiration_date);

        // check if new period overlaps with current period
        if ($value_start < $compare_end) {
            $fail('The new benefit period overlaps with the current period.');
            return;
        }
    }
}
