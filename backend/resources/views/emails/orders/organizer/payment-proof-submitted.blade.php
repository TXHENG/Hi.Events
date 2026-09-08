<x-mail::message>
# {{ __('Payment proof submitted') }}

{{ __(':customer has submitted proof of payment for order :reference.', ['customer' => trim($order->getFirstName().' '.$order->getLastName()), 'reference' => $order->getPublicId()]) }}

<x-mail::button :url="$orderUrl">
{{ __('Review payment proof') }}
</x-mail::button>

{{ __('Review the receipt before marking the order as paid.') }}
</x-mail::message>
