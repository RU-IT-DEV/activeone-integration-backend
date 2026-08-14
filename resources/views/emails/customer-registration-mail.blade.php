<x-mail::message>
# Welcome to {{ config('app.name') }}!

Hi {{ $customer_name }},

Thank you for registering with **{{ config('app.name') }}**.

Please confirm your email address by clicking the button below:

<x-mail::button :url="$verificationUrl">
Confirm Email Address
</x-mail::button>

If you did not create an account with us, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>