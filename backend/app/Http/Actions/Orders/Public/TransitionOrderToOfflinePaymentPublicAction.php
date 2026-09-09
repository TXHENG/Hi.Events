<?php

namespace HiEvents\Http\Actions\Orders\Public;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Order\OrderResourcePublic;
use HiEvents\Services\Application\Handlers\Order\DTO\TransitionOrderToOfflinePaymentPublicDTO;
use HiEvents\Services\Application\Handlers\Order\TransitionOrderToOfflinePaymentHandler;
use Illuminate\Http\JsonResponse;
use HiEvents\Http\Request\Order\TransitionOrderToOfflinePaymentPublicRequest;

class TransitionOrderToOfflinePaymentPublicAction extends BaseAction
{
    public function __construct(
        private readonly TransitionOrderToOfflinePaymentHandler $initializeOrderOfflinePaymentPublicHandler,
    ) {}

    public function __invoke(TransitionOrderToOfflinePaymentPublicRequest $request, int $eventId, string $orderShortId): JsonResponse
    {
        $order = $this->initializeOrderOfflinePaymentPublicHandler->handle(
            TransitionOrderToOfflinePaymentPublicDTO::fromArray([
                'eventId' => $eventId,
                'orderShortId' => $orderShortId,
                'proof' => $request->file('proof'),
                'paymentReference' => $request->validated('payment_reference'),
            ]),
        );

        return $this->resourceResponse(
            resource: OrderResourcePublic::class,
            data: $order,
        );
    }
}
