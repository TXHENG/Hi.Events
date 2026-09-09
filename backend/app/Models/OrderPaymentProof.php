<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class OrderPaymentProof extends BaseModel
{
    use SoftDeletes;

    /**
     * Keep the serialized value compatible with the payment-proof domain object.
     * Eloquent otherwise returns a Carbon instance after a review timestamp is set.
     */
    protected function getCastMap(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }
}
