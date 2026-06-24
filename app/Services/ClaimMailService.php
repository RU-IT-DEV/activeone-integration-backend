<?php

namespace App\Services;
class ClaimMailService 
{
    public function getCCEmails($claim)
    {
        // Get Member Company
        $memberCompany = $claim->member?->company;
        $support_sentence_template = $memberCompany->support_email_sentence_template;
        $arr_supports = $memberCompany->support;
        $support_emails = [];

        if (str_contains($support_sentence_template, "{{emails}}")) {
            $support_emails = $arr_supports->pluck('email')->toArray();
        } else if (str_contains($support_sentence_template, "{{emails0}}")) {
            $str_support_sentence = $support_sentence_template;
            foreach ($support_emails as $key => $value) {
                if (str_contains($str_support_sentence, "{{emails{$key}}}")) {
                    $support_emails[] = $value;
                }
            }
        } else if (str_contains($support_sentence_template, "{{bentype.email}}")) {
            $support_email = $arr_supports->where('label', $claim->type)->first();
            if ($support_email) {
                $support_emails[] = $support_email->email;
            }
        }

        return $support_emails;
    }

}