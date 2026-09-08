<?php

namespace HiEvents\DomainObjects;

class OrderPaymentProofDomainObject extends AbstractDomainObject
{
    final public const SINGULAR_NAME = 'order_payment_proof';
    final public const PLURAL_NAME = 'order_payment_proofs';

    protected int $id;
    protected int $event_id;
    protected int $order_id;
    protected string $disk;
    protected string $path;
    protected string $original_filename;
    protected string $mime_type;
    protected int $size;
    protected ?string $payment_reference = null;
    protected string $status;
    protected ?string $rejection_reason = null;
    protected ?int $reviewed_by_user_id = null;
    protected ?string $reviewed_at = null;
    protected ?string $created_at = null;
    protected ?string $updated_at = null;
    protected ?string $deleted_at = null;

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public function setId(int $value): self { $this->id = $value; return $this; }
    public function getId(): int { return $this->id; }
    public function setEventId(int $value): self { $this->event_id = $value; return $this; }
    public function getEventId(): int { return $this->event_id; }
    public function setOrderId(int $value): self { $this->order_id = $value; return $this; }
    public function getOrderId(): int { return $this->order_id; }
    public function setDisk(string $value): self { $this->disk = $value; return $this; }
    public function getDisk(): string { return $this->disk; }
    public function setPath(string $value): self { $this->path = $value; return $this; }
    public function getPath(): string { return $this->path; }
    public function setOriginalFilename(string $value): self { $this->original_filename = $value; return $this; }
    public function getOriginalFilename(): string { return $this->original_filename; }
    public function setMimeType(string $value): self { $this->mime_type = $value; return $this; }
    public function getMimeType(): string { return $this->mime_type; }
    public function setSize(int $value): self { $this->size = $value; return $this; }
    public function getSize(): int { return $this->size; }
    public function setPaymentReference(?string $value): self { $this->payment_reference = $value; return $this; }
    public function getPaymentReference(): ?string { return $this->payment_reference; }
    public function setStatus(string $value): self { $this->status = $value; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setRejectionReason(?string $value): self { $this->rejection_reason = $value; return $this; }
    public function getRejectionReason(): ?string { return $this->rejection_reason; }
    public function setReviewedByUserId(?int $value): self { $this->reviewed_by_user_id = $value; return $this; }
    public function getReviewedByUserId(): ?int { return $this->reviewed_by_user_id; }
    public function setReviewedAt(?string $value): self { $this->reviewed_at = $value; return $this; }
    public function getReviewedAt(): ?string { return $this->reviewed_at; }
    public function setCreatedAt(?string $value): self { $this->created_at = $value; return $this; }
    public function getCreatedAt(): ?string { return $this->created_at; }
    public function setUpdatedAt(?string $value): self { $this->updated_at = $value; return $this; }
    public function getUpdatedAt(): ?string { return $this->updated_at; }
    public function setDeletedAt(?string $value): self { $this->deleted_at = $value; return $this; }
    public function getDeletedAt(): ?string { return $this->deleted_at; }
}
