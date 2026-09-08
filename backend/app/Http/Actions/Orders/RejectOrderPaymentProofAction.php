<?php

namespace HiEvents\Http\Actions\Orders;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Order\RejectOrderPaymentProofRequest;
use HiEvents\Resources\Order\OrderPaymentProofResource;
use HiEvents\Services\Domain\Order\OrderPaymentProofService;
use Illuminate\Http\JsonResponse;

class RejectOrderPaymentProofAction extends BaseAction
{
    public function __construct(private readonly OrderPaymentProofService $paymentProofService) {}

    public function __invoke(RejectOrderPaymentProofRequest $request, int $eventId, int $orderId, int $paymentProofId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->resourceResponse(
            OrderPaymentProofResource::class,
            $this->paymentProofService->reject(
                $eventId,
                $orderId,
                $paymentProofId,
                $this->getAuthenticatedUser()->getId(),
                $request->validated('reason'),
            ),
        );
    }
}
