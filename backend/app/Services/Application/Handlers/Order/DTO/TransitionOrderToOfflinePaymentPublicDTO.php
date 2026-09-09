<?php

namespace HiEvents\Services\Application\Handlers\Order\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use Illuminate\Http\UploadedFile;

class TransitionOrderToOfflinePaymentPublicDTO extends BaseDTO
{
    public function __construct(
        public readonly int $eventId,
        public readonly string $orderShortId,
        public readonly ?UploadedFile $proof = null,
        public readonly ?string $paymentReference = null,
    ) {}
}
