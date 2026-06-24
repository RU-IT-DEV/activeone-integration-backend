@component('mail::message', ['logoUrl' => $logoUrl])
<div class="enforced-style">
<p>Dear {{ ucwords(strtolower($claimResponse->claim->member->first_name." ".$claimResponse->claim->member->last_name)) }}, </p>

@if ($status == 'Approved')
<p>Your claim has been approved, with the following details:</p>
@elseif ($status == 'Partially approved')
<p>Your claim is partially approved, with the following details:</p>
@elseif ($status == 'Rejected')
<p>Your claim is disapproved, with the following details:</p>
@endif

@component('mail::panel')
<div class="enforced-style">
    <p><strong>Claim No:</strong> {{ $claimResponse->claim->claim_id }}</p>
    <p><strong>Member Name:</strong> {{ $claimResponse->claim->member->first_name }} {{ $claimResponse->claim->member->last_name }}</p>
    <p><strong>Claim Coverage:</strong> {{ $claimResponse->claim->coverage }}</p>
    @if($claimResponse->claim->category)
    <p><strong>Claim Category:</strong> {{ $claimResponse->claim->category }}</p>
    @endif
    <p><strong>Requested Amount:</strong> Php {{ number_format($claimResponse->claim->amount, 2) }}</p>
    <p><strong>Approved Amount:</strong> Php {{ number_format($approvedAmount, 2) }}</p>
    <p><strong>Disapproved Amount:</strong> Php {{ number_format($rejectedAmount, 2) }}</p>
    <p><strong>Status:</strong> <span class="text-{{ strtolower($status) }}">{{ $status == 'Rejected' ? 'Disapproved' : $status }}</span></p>
    <p><strong>Invoice Date:</strong> {{ \Carbon\Carbon::parse($claimResponse->claim->service_date)->format('m/d/Y') }}</p>
    <p><strong>Date Submitted:</strong> {{ \Carbon\Carbon::parse($claimResponse->claim->created_at)->format('m/d/Y') }}</p>
    <p><strong>Remarks:</strong></p>
    <textarea 
        readonly 
        style="width: 100%; height: 100px; resize: none; border: none; padding: 10px; background-color: none; color: #718096;"
    >{{ $claimResponse->remarks }}</textarea>
</div>
@endcomponent

Thank you,  <br>
<b>Flexicare Support Team</b>
</div>
@endcomponent

<style>
.enforced-style {
background-color: white;
font-size: 12px;
}
.enforced-style * {
    background-color: inherit;
    font-size: inherit;
}

.text-rejected {
    color: #B00020;
    font-weight: bold;
}

.text-approved {
    color: #4CAF50;
    font-weight: bold;
}

.text-partially.approved {
    color: #FB8C00;
    font-weight: bold;
}
</style>

