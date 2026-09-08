<?php

namespace HiEvents\Http\Actions\Orders\Public;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Order\OrderPaymentProofService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadOrderPaymentProofPublicAction extends BaseAction
{
    public function __construct(private readonly OrderPaymentProofService $paymentProofService) {}

    public function __invoke(int $eventId, string $orderShortId, int $paymentProofId): StreamedResponse
    {
        return $this->paymentProofService->downloadForPublicOrder($eventId, $orderShortId, $paymentProofId);
    }
}
