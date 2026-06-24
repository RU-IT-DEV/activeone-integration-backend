@component('mail::message', ['logoUrl' => $logoUrl])
<b>Dear {{ $employeeName }},</b>

<p>We have successfully received your claim documents.</p>

<p>
    <b>Claim Number:</b> {{ $claimId }} <br>
    <b>Date Received:</b> {{ $receiveDate }}
</p>

<p>Our team will review your documents accordingly.</p>

<p>Thank you,<br>
Flexicare Support Team</p>
@endcomponent
