@component('mail::message')
# Contract Expiration Notice

Dear User,

This is a reminder that the contract for **{{ $companyName }}** is nearing its expiration date.

@component('mail::panel')
**Expiration Date:** {{ $expirationDate }}  
**Days Remaining:** {{ $daysRemaining }}
@endcomponent

Please take necessary action to renew or update the contract.

<!-- @component('mail::button', ['url' => 'https://example.com/contracts'])
View Contracts
@endcomponent -->

Thank you for your attention.<br>
This is system generated email. Please DO NOT REPPLY

Best regards,  
{{ config('app.name') }}
@endcomponent
