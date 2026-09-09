<?php

namespace Tests\Unit\Models;

use HiEvents\Models\OrderPaymentProof;
use Tests\TestCase;

class OrderPaymentProofTest extends TestCase
{
    public function test_review_timestamp_serializes_as_a_string_for_the_domain_object(): void
    {
        $proof = new OrderPaymentProof([
            'reviewed_at' => now(),
        ]);

        $attributes = $proof->attributesToArray();

        $this->assertIsString($attributes['reviewed_at']);
    }
}
