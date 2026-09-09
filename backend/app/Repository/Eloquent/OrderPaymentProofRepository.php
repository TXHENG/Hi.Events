<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\Enums\OrderPaymentProofStatus;
use HiEvents\DomainObjects\OrderPaymentProofDomainObject;
use HiEvents\Models\OrderPaymentProof;
use HiEvents\Repository\Interfaces\OrderPaymentProofRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<OrderPaymentProofDomainObject>
 */
class OrderPaymentProofRepository extends BaseRepository implements OrderPaymentProofRepositoryInterface
{
    public function findPendingForOrder(int $orderId): ?OrderPaymentProofDomainObject
    {
        return $this->runQuery(fn () => $this->handleSingleResult(
            $this->model
                ->where('order_id', $orderId)
                ->where('status', OrderPaymentProofStatus::PENDING->value)
                ->lockForUpdate()
                ->first(),
        ));
    }

    public function findForOrderByIdForUpdate(int $orderId, int $paymentProofId): ?OrderPaymentProofDomainObject
    {
        return $this->runQuery(fn () => $this->handleSingleResult(
            $this->model
                ->where('order_id', $orderId)
                ->where('id', $paymentProofId)
                ->lockForUpdate()
                ->first(),
        ));
    }

    public function findForOrder(int $orderId): Collection
    {
        return $this->findWhere(
            where: [['order_id', '=', $orderId]],
            orderAndDirections: [new Value\OrderAndDirection('created_at', 'desc')],
        );
    }

    public function getDomainObject(): string
    {
        return OrderPaymentProofDomainObject::class;
    }

    protected function getModel(): string
    {
        return OrderPaymentProof::class;
    }
}
