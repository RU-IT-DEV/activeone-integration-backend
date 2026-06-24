<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\RejectionReason; 

class RejectionReasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reasons = [
            'Not Covered',
            'No Coverage for Employees',
            'No Coverage for Dependent',
            'Currency is not in PHP',
            'Insufficient Balance',
            'Inactive Employee',
            'Invalid Receipt date',
            'No response within 7 days',
            'Duplicate Request',
            'Not Registered',
            'Exceeded Optical Coverage up to 10K',
            'Receipt is not under the Employee Name',
            'Invalid docs submitted',
            'Exceeded Limit',
            'Employee Request - Cancelled Submission',
            'Employee is not included as a Passenger',
            'Late Submission',
        ];

        foreach ($reasons as $reason) {
            RejectionReason::firstOrCreate(['reason' => $reason]);
        }
    }
}
