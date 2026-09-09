<?php

namespace HiEvents\Http\Actions\Orders;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Order\OrderPaymentProofResource;
use HiEvents\Services\Domain\Order\OrderPaymentProofService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ApproveOrderPaymentProofAction extends BaseAction
{
    public function __construct(private readonly OrderPaymentProofService $paymentProofService) {}

    public function __invoke(int $eventId, int $orderId, int $paymentProofId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $proof = $this->paymentProofService->approve(
                $eventId,
                $orderId,
                $paymentProofId,
                $this->getAuthenticatedUser()->getId(),
            );
        } catch (ResourceConflictException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->resourceResponse(OrderPaymentProofResource::class, $proof);
    }
}
