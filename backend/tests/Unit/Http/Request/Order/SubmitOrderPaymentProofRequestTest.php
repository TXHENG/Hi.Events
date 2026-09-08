<?php

namespace Tests\Unit\Http\Request\Order;

use HiEvents\Http\Request\Order\SubmitOrderPaymentProofRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SubmitOrderPaymentProofRequestTest extends TestCase
{
    public function test_accepts_supported_file_types_and_payment_reference(): void
    {
        foreach ([
            UploadedFile::fake()->image('receipt.jpg'),
            UploadedFile::fake()->image('receipt.png'),
            UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
        ] as $proof) {
            $validator = Validator::make(
                ['proof' => $proof, 'payment_reference' => 'BANK-TRANSFER-123'],
                (new SubmitOrderPaymentProofRequest)->rules()
            );

            $this->assertFalse($validator->errors()->has('proof'));
        }
    }

    public function test_rejects_unsupported_or_oversized_files(): void
    {
        foreach ([
            UploadedFile::fake()->create('receipt.txt', 100, 'text/plain'),
            UploadedFile::fake()->create('receipt.pdf', 10241, 'application/pdf'),
        ] as $proof) {
            $validator = Validator::make(
                ['proof' => $proof],
                (new SubmitOrderPaymentProofRequest)->rules()
            );

            $this->assertTrue($validator->errors()->has('proof'));
        }
    }

    public function test_requires_a_proof_file(): void
    {
        $validator = Validator::make([], (new SubmitOrderPaymentProofRequest)->rules());

        $this->assertTrue($validator->errors()->has('proof'));
    }
}
