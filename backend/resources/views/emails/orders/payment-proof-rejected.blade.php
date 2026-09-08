<x-mail::message>
# {{ __('Payment proof needs attention') }}

{{ __('The payment proof for your order :reference could not be accepted.', ['reference' => $order->getPublicId()]) }}

{{ __('Reason: :reason', ['reason' => $rejectionReason]) }}

{{ __('Please return to your order page and submit a new proof of payment.') }}

{{ __('Thanks,') }}<br>
{{ $organizer->getName() }}
</x-mail::message>
