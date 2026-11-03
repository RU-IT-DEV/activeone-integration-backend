<x-mail::message>
# ActiveOne Order Partially Approved

Hi {{ $order->customer_name ?? 'there' }},

We're sorry, but we were unable to process some of your ActiveOne pharmacy order.

**Order Number:** {{ $order->shopify_order_name }}<br>
**Order Reference:** {{ $reference_number }}<br>
**Order Date:** {{ $order_datetime }}

## Updated Order Items

@foreach ($order->lineItems as $item)
@if (!is_null($item->reason))
* **{{ $item->title }}** — Quantity: {{ $item->quantity }}<br>
**Reason:** {{ $item->reason }}<br>
@endif
@endforeach

@if ($order->lineItemsTrashed->isNotEmpty())
## Removed Order Items
@endif
@foreach ($order->lineItemsTrashed as $item)
* **{{ $item->title }}** — Quantity: {{ $item->quantity }}<br>
**Reason:** {{ $item->reason }}<br>
@endforeach

Please review the information and prescription associated with your order. If applicable, you may place a new order with the necessary corrections or an updated prescription.

If you need assistance, please contact the ActiveOne RX team.

Thank you,<br>
**ActiveOne RX**

### DO NOT REPLY TO THIS EMAIL
</x-mail::message>
