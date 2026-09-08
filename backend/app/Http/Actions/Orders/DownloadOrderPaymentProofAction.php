<?php

namespace HiEvents\Http\Actions\Orders;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Order\OrderPaymentProofService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadOrderPaymentProofAction extends BaseAction
{
    public function __construct(private readonly OrderPaymentProofService $paymentProofService) {}

    public function __invoke(int $eventId, int $orderId, int $paymentProofId): StreamedResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->paymentProofService->downloadForOrganizer($eventId, $orderId, $paymentProofId);
    }
}
