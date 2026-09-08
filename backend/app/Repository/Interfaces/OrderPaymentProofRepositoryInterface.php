<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\OrderPaymentProofDomainObject;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<OrderPaymentProofDomainObject>
 */
interface OrderPaymentProofRepositoryInterface extends RepositoryInterface
{
    public function findPendingForOrder(int $orderId): ?OrderPaymentProofDomainObject;

    /** @return Collection<OrderPaymentProofDomainObject> */
    public function findForOrder(int $orderId): Collection;
}
