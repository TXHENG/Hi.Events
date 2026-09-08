<?php

namespace HiEvents\Mail\Organizer;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Helper\Url;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrderPaymentProofSubmittedMail extends BaseMail
{
    public function __construct(
        private readonly EventDomainObject $event,
        private readonly OrderDomainObject $order,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Payment proof submitted for :event', ['event' => $this->event->getTitle()]));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.organizer.payment-proof-submitted',
            with: [
                'event' => $this->event,
                'order' => $this->order,
                'orderUrl' => sprintf(Url::getFrontEndUrlFromConfig(Url::ORGANIZER_ORDER_SUMMARY), $this->event->getId(), $this->order->getId()),
            ],
        );
    }
}
