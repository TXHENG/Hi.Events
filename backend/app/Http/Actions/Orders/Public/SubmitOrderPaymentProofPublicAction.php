<?php

namespace HiEvents\Http\Actions\Orders\Public;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Order\SubmitOrderPaymentProofRequest;
use HiEvents\Resources\Order\OrderPaymentProofResource;
use HiEvents\Services\Domain\Order\OrderPaymentProofService;
use Illuminate\Http\JsonResponse;

class SubmitOrderPaymentProofPublicAction extends BaseAction
{
    public function __construct(private readonly OrderPaymentProofService $paymentProofService) {}

    public function __invoke(SubmitOrderPaymentProofRequest $request, int $eventId, string $orderShortId): JsonResponse
    {
        $proof = $this->paymentProofService->submit(
            eventId: $eventId,
            orderShortId: $orderShortId,
            file: $request->file('proof'),
            paymentReference: $request->validated('payment_reference'),
        );

        return $this->resourceResponse(OrderPaymentProofResource::class, $proof);
    }
}
