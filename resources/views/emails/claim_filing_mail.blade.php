@component('mail::message', ['logoUrl' => $logoUrl])
<div class="enforced-style">

<p>Dear {{ ucwords(strtolower($claim->member->first_name." ".$claim->member->last_name)) }}, </p>


<p>Your Claim No. {{ $claim->claim_id }} has been submitted successfully.</p>

@component('mail::panel')
<div class="enforced-style">
    <p><strong>Member Name:</strong> {{$claim->member->first_name}} {{$claim->member->last_name}}</p>
    <!-- <p><strong>Benefit Name:</strong> Benefit</p> -->
    <p><strong>Claim Coverage:</strong> {{ $claim->coverage }}</p>
    @if($claim->category)
    <p><strong>Claim Category:</strong> {{ $claim->category }}</p>
    @endif
    <p><strong>Claim Amount:</strong> Php {{ number_format($claim->total_amount, 2) }}</p>
    <!-- <p><strong>Invoice Date:</strong> {{ \Carbon\Carbon::parse($claim->service_date)->format('m/d/Y') }}</p> -->
    <p><strong>Date Submitted:</strong>  {{ \Carbon\Carbon::parse($claim->created_at)->format('m/d/Y') }}</p>
    <!-- @if($claim->receipt)
        <p><strong>Receipt:</strong> </p>
        <p>
            <a href="{{$myUrl}}flexben/object?fileName={{ $claim->receipt }}">
                <img src="{{$myUrl}}flexben/object?fileName={{ $claim->receipt }}" alt="Download Receipt" style="height: 180px" />
            </a>
        </p>
    @endif -->
</div>
@endcomponent
<p>
    Kindly expect an update within 24 hours. {!! $support_sentence !!}
</p>
<p>Thank you,<br>
<b>Flexicare Support Team</b></p>
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
</style>
