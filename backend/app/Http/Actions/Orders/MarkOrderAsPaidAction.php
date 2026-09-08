<?php

namespace HiEvents\Http\Actions\Orders;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Enums\OrderPaymentProofStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Order\OrderResource;
use HiEvents\Repository\Interfaces\OrderPaymentProofRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\MarkOrderAsPaidDTO;
use HiEvents\Services\Application\Handlers\Order\MarkOrderAsPaidHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class MarkOrderAsPaidAction extends BaseAction
{
    public function __construct(
        private readonly MarkOrderAsPaidHandler $markOrderAsPaidHandler,
        private readonly OrderPaymentProofRepositoryInterface $paymentProofRepository,
    ) {}

    public function __invoke(int $eventId, int $orderId): JsonResponse|Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $order = $this->markOrderAsPaidHandler->handle(new MarkOrderAsPaidDTO($eventId, $orderId));
            $pendingProof = $this->paymentProofRepository->findPendingForOrder($orderId);
            if ($pendingProof !== null) {
                $this->paymentProofRepository->updateFromArray($pendingProof->getId(), [
                    'status' => OrderPaymentProofStatus::APPROVED->value,
                    'reviewed_by_user_id' => $this->getAuthenticatedUser()->getId(),
                    'reviewed_at' => now(),
                ]);
            }
        } catch (ResourceConflictException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->resourceResponse(
            resource: OrderResource::class,
            data: $order,
        );
    }
}
