<?php

namespace HiEvents\Http\Request\Order;

use HiEvents\Http\Request\BaseRequest;

class TransitionOrderToOfflinePaymentPublicRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
