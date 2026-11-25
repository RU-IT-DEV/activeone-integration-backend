<x-mail::message>
# Daily Report @ {{ $reportDate }}{{ $time }}

Attached is the ActiveOne Daily Order Report for {{ $reportDate }}, covering midnight to 8:30 AM Philippine time.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>