<?php

namespace Tests\Unit\Http\Request\Order;

use HiEvents\Http\Request\Order\TransitionOrderToOfflinePaymentPublicRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TransitionOrderToOfflinePaymentPublicRequestTest extends TestCase
{
    public function test_accepts_an_optional_supported_payment_proof_and_reference(): void
    {
        foreach ([
            [],
            ['proof' => UploadedFile::fake()->image('receipt.jpg')],
            ['proof' => UploadedFile::fake()->image('receipt.png')],
            ['proof' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf')],
            ['payment_reference' => 'BANK-TRANSFER-123'],
        ] as $payload) {
            $validator = Validator::make(
                $payload,
                (new TransitionOrderToOfflinePaymentPublicRequest)->rules(),
            );

            $this->assertFalse($validator->fails());
        }
    }

    public function test_rejects_unsupported_or_oversized_files_and_long_references(): void
    {
        foreach ([
            ['proof' => UploadedFile::fake()->create('receipt.txt', 100, 'text/plain')],
            ['proof' => UploadedFile::fake()->create('receipt.pdf', 10241, 'application/pdf')],
            ['payment_reference' => str_repeat('a', 256)],
        ] as $payload) {
            $validator = Validator::make(
                $payload,
                (new TransitionOrderToOfflinePaymentPublicRequest)->rules(),
            );

            $this->assertTrue($validator->fails());
        }
    }
}
