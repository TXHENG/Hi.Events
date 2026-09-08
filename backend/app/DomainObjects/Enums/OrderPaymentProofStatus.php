<?php

namespace HiEvents\DomainObjects\Enums;

enum OrderPaymentProofStatus: string
{
    use BaseEnum;

    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
}
