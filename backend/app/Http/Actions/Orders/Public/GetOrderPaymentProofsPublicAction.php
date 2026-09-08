<?php

namespace HiEvents\Http\Actions\Orders\Public;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Order\OrderPaymentProofResource;
use HiEvents\Services\Domain\Order\OrderPaymentProofService;
use Illuminate\Http\JsonResponse;

class GetOrderPaymentProofsPublicAction extends BaseAction
{
    public function __construct(private readonly OrderPaymentProofService $paymentProofService) {}

    public function __invoke(int $eventId, string $orderShortId): JsonResponse
    {
        return $this->resourceResponse(
            OrderPaymentProofResource::class,
            $this->paymentProofService->getForPublicOrder($eventId, $orderShortId),
        );
    }
}
