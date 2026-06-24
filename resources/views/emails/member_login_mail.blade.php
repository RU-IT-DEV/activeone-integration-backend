@component('mail::message', ['logoUrl' => $logoUrl])
<div class="enforced-style">
<p>Dear {{ ucwords(strtolower($name)) }},</p>

<p>We received a request to sign in to Flexben using this email address.</p> 
<p>If you wish to sign in with your {{ $email }} account, please click this link. </p>
<x-mail::button :url="$signInLink" color="success">Sign-in</x-mail::button>
<p>If you did not make this request, please disregard this email.</p>

<p>Thank you,<br>
{{ config('app.name') }}
</p>
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
