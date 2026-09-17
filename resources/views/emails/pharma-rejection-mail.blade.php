<x-mail::message>
# ActiveOne Order Unable to Process

Hi {{ $order->customer_name ?? 'there' }},

We're sorry, but we were unable to process your ActiveOne pharmacy order.

**Order Reference:** {{ $reference_number }}<br>
**Order Date:** {{ $order_datetime }}

## Order Items

@foreach ($order->lineItems as $item)
* **{{ $item->title }}** — Quantity: {{ $item->quantity }}
@endforeach

## Reason for Cancellation

{{ $rejection_reason_message }}

Please review the information and prescription associated with your order. If applicable, you may place a new order with the necessary corrections or an updated prescription.

If you need assistance, please contact the ActiveOne RX team.

Thank you,<br>
**ActiveOne RX**

### DO NOT REPLY TO THIS EMAIL
</x-mail::message>
