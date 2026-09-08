<?php

namespace HiEvents\Resources\Order;

use HiEvents\DomainObjects\OrderPaymentProofDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin OrderPaymentProofDomainObject
 */
class OrderPaymentProofResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'payment_reference' => $this->getPaymentReference(),
            'original_filename' => $this->getOriginalFilename(),
            'mime_type' => $this->getMimeType(),
            'size' => $this->getSize(),
            'status' => $this->getStatus(),
            'rejection_reason' => $this->getRejectionReason(),
            'reviewed_at' => $this->getReviewedAt(),
            'created_at' => $this->getCreatedAt(),
        ];
    }
}
