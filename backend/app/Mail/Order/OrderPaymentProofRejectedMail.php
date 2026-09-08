<?php

namespace HiEvents\Mail\Order;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Mail\BaseMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrderPaymentProofRejectedMail extends BaseMail
{
    public function __construct(
        private readonly EventDomainObject $event,
        private readonly OrderDomainObject $order,
        private readonly OrganizerDomainObject $organizer,
        private readonly EventSettingDomainObject $eventSettings,
        private readonly string $rejectionReason,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: $this->eventSettings->getSupportEmail(),
            subject: __('Payment proof needs attention for :event', ['event' => $this->event->getTitle()]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.payment-proof-rejected',
            with: [
                'event' => $this->event,
                'order' => $this->order,
                'organizer' => $this->organizer,
                'rejectionReason' => $this->rejectionReason,
            ],
        );
    }
}
