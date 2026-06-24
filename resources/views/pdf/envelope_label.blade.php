<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Envelope Label</title>
    <style>
        /* === General Layout === */
        body {
            font-family: Arial, sans-serif;
            font-size: 13px;
            color: #000;
            margin: 30px;
            line-height: 1.4;
            background-color: #ffffff; /* force white background */
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            background-color: #ffffff; /* white table background */
        }

        td, th {
            padding: 8px 10px;
            vertical-align: middle;
            background-color: #ffffff; /* ensure all cells are white */
        }

        /* === Labels === */
        td:first-child {
            font-weight: bold;
            width: 35%;
            border: 1px solid #000;
            background-color: #ffffff; 
        }

        /* === Values === */
        td:last-child {
            border: 1px solid #000;
            width: 65%;
            background-color: #ffffff; /* value cells white */
        }

        /* === QR Code Section === */
        .qr-wrapper {
            text-align: right;
            padding-top: 10px;
        }

        .qr-label {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
            margin-bottom: 4px;
        }

        .qr-code img {
            border: 1px solid #000;
            padding: 4px;
            background: #fff;
            width: 90px;
            height: 90px;
        }

        /* === Confidential Notice === */
        .footer-text {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            margin-top: 15px;
            letter-spacing: 1px;
        }
    </style>

</head>
<body>
    <table>
        <tr>
            <td colspan="2" style="text-align: left; padding: 10px 0;">
                <img src="{{ $flexiLogo ?: 'https://flexben.web.app/flexicare-logo.png' }}" alt="Flexicare Logo" height="100px">
            </td>
        </tr>
        <tr>
            <td>Employee Name:</td>
            <td>{{ $claim->member->full_name ?? $claim->member->first_name.' '.$claim->member->last_name }}</td>
        </tr>
        <tr>
            <td>Claim ID:</td>
            <td>{{ $claimId }}</td>
        </tr>
        <tr>
            <td>Reimbursement Type:</td>
            <td style="text-transform: capitalize;">
                {{ $claim_type === 'choicepot' ? 'CHOICE POT' : strtoupper($claim_type) }}
            </td>
        </tr>
        <tr>
            <td>Division:</td>
            <td>{{ $claim->member->division ?? '---' }}</td>
        </tr>
        <tr>
            <td>Submission Date:</td>
            <td>{{ $claim->created_at->format('m/d/Y') }}</td>
        </tr>
        <tr>
            <td colspan="2">
                <div class="qr-wrapper">
                    <!-- <div class="qr-label">Claim ID QR Code</div> -->
                    <div class="qr-label"></div>
                    <div class="qr-code">
                        <img src="data:image/svg+xml;base64,{{ $qrCodeBase64 }}" alt="QR Code">
                    </div>
                </div>
            </td>
        </tr>
    </table>

    

    <p class="footer-text">CONFIDENTIAL</p>
</body>
</html>
