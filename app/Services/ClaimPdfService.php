<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\MemberClaims;

class ClaimPdfService
{
    public function generate(MemberClaims $claim, string $claimId): ?string
    {
        if (!in_array($claim->type, ['fsa', 'choicepot', 'reimbursement'])) {
            return null;
        }

        $qrCode = base64_encode(
            QrCode::size(100)->generate($claim->claim_id)
        );

        return Pdf::loadView('pdf.envelope_label', [
            'claimId' => $claimId,
            'claim' => $claim,
            'claim_type' => $claim->type,
            'qrCodeBase64' => $qrCode,
            'flexiLogo' => 'https://flexben.web.app/flexicare-logo.png',
        ])
        ->setOptions([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
        ])
        ->output();
    }
}