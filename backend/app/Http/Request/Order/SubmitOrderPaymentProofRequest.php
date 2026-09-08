<?php

namespace HiEvents\Http\Request\Order;

use HiEvents\Http\Request\BaseRequest;

class SubmitOrderPaymentProofRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
