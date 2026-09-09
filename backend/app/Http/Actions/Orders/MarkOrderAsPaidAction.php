<?php

namespace HiEvents\Http\Actions\Orders;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Order\OrderResource;
use HiEvents\Services\Domain\Order\OrderPaymentProofService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class MarkOrderAsPaidAction extends BaseAction
{
    public function __construct(
        private readonly OrderPaymentProofService $paymentProofService,
    ) {}

    public function __invoke(int $eventId, int $orderId): JsonResponse|Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $order = $this->paymentProofService->markOrderAsPaid(
                $eventId,
                $orderId,
                $this->getAuthenticatedUser()->getId(),
            );
        } catch (ResourceConflictException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->resourceResponse(
            resource: OrderResource::class,
            data: $order,
        );
    }
}
