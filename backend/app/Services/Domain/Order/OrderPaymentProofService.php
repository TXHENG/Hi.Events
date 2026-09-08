<?php

namespace HiEvents\Services\Domain\Order;

use HiEvents\DomainObjects\Enums\OrderPaymentProofStatus;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderPaymentProofDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Mail\Order\OrderPaymentProofRejectedMail;
use HiEvents\Mail\Organizer\OrderPaymentProofSubmittedMail;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderPaymentProofRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class OrderPaymentProofService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderPaymentProofRepositoryInterface $paymentProofRepository,
        private readonly EventSettingsRepositoryInterface $eventSettingsRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly MarkOrderAsPaidService $markOrderAsPaidService,
        private readonly FilesystemManager $filesystemManager,
        private readonly ConfigRepository $config,
        private readonly DatabaseManager $databaseManager,
        private readonly Mailer $mailer,
    ) {}

    /**
     * @throws Throwable
     */
    public function submit(int $eventId, string $orderShortId, UploadedFile $file, ?string $paymentReference): OrderPaymentProofDomainObject
    {
        $order = $this->getEligibleOrder($eventId, $orderShortId);
        $disk = $this->config->get('filesystems.private');
        $filename = Str::uuid().'.'.$file->guessExtension();
        $path = $this->filesystemManager->disk($disk)->putFileAs(
            'payment-proofs/'.$order->getId(),
            $file,
            $filename,
        );

        if ($path === false) {
            throw new ResourceConflictException(__('Could not store payment proof'));
        }

        try {
            $proof = $this->databaseManager->transaction(function () use ($order, $eventId, $disk, $path, $file, $paymentReference) {
                if ($this->paymentProofRepository->findPendingForOrder($order->getId()) !== null) {
                    throw new ResourceConflictException(__('A payment proof is already awaiting review'));
                }

                return $this->paymentProofRepository->create([
                    'event_id' => $eventId,
                    'order_id' => $order->getId(),
                    'disk' => $disk,
                    'path' => $path,
                    'original_filename' => basename($file->getClientOriginalName()),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'payment_reference' => $paymentReference === null ? null : trim($paymentReference),
                    'status' => OrderPaymentProofStatus::PENDING->value,
                ]);
            });
        } catch (Throwable $e) {
            $this->filesystemManager->disk($disk)->delete($path);
            throw $e;
        }

        $event = $this->getEventWithPaymentContext($eventId);
        $this->mailer
            ->to($event->getOrganizer()->getEmail())
            ->send(new OrderPaymentProofSubmittedMail($event, $order));

        return $proof;
    }

    /** @return Collection<OrderPaymentProofDomainObject> */
    public function getForPublicOrder(int $eventId, string $orderShortId): Collection
    {
        $order = $this->getOrderForEvent($eventId, $orderShortId);

        return $this->paymentProofRepository->findForOrder($order->getId());
    }

    /** @return Collection<OrderPaymentProofDomainObject> */
    public function getForOrganizer(int $eventId, int $orderId): Collection
    {
        $this->getOrderForEventId($eventId, $orderId);

        return $this->paymentProofRepository->findForOrder($orderId);
    }

    /**
     * @throws Throwable
     */
    public function approve(int $eventId, int $orderId, int $paymentProofId, int $reviewerId): OrderPaymentProofDomainObject
    {
        return $this->databaseManager->transaction(function () use ($eventId, $orderId, $paymentProofId, $reviewerId) {
            $order = $this->getOrderForEventId($eventId, $orderId);
            $proof = $this->getPendingProof($order, $paymentProofId);

            $this->markOrderAsPaidService->markOrderAsPaid($orderId, $eventId);

            return $this->paymentProofRepository->updateFromArray($proof->getId(), [
                'status' => OrderPaymentProofStatus::APPROVED->value,
                'reviewed_by_user_id' => $reviewerId,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);
        });
    }

    /**
     * @throws Throwable
     */
    public function reject(int $eventId, int $orderId, int $paymentProofId, int $reviewerId, string $reason): OrderPaymentProofDomainObject
    {
        [$proof, $order] = $this->databaseManager->transaction(function () use ($eventId, $orderId, $paymentProofId, $reviewerId, $reason) {
            $order = $this->getOrderForEventId($eventId, $orderId);
            $proof = $this->getPendingProof($order, $paymentProofId);

            $updatedProof = $this->paymentProofRepository->updateFromArray($proof->getId(), [
                'status' => OrderPaymentProofStatus::REJECTED->value,
                'reviewed_by_user_id' => $reviewerId,
                'reviewed_at' => now(),
                'rejection_reason' => trim($reason),
            ]);

            return [$updatedProof, $order];
        });

        $event = $this->getEventWithPaymentContext($eventId);
        $this->mailer
            ->to($order->getEmail())
            ->locale($order->getLocale())
            ->send(new OrderPaymentProofRejectedMail(
                event: $event,
                order: $order,
                organizer: $event->getOrganizer(),
                eventSettings: $event->getEventSettings(),
                rejectionReason: $proof->getRejectionReason(),
            ));

        return $proof;
    }

    public function downloadForPublicOrder(int $eventId, string $orderShortId, int $paymentProofId): StreamedResponse
    {
        $order = $this->getOrderForEvent($eventId, $orderShortId);

        return $this->download($this->getProofForOrder($order->getId(), $paymentProofId));
    }

    public function downloadForOrganizer(int $eventId, int $orderId, int $paymentProofId): StreamedResponse
    {
        $this->getOrderForEventId($eventId, $orderId);

        return $this->download($this->getProofForOrder($orderId, $paymentProofId));
    }

    private function getEligibleOrder(int $eventId, string $orderShortId): OrderDomainObject
    {
        $order = $this->getOrderForEvent($eventId, $orderShortId);
        $settings = $this->eventSettingsRepository->findFirstWhere(['event_id' => $eventId]);

        if (! $settings instanceof EventSettingDomainObject || ! $settings->getAllowOfflinePaymentProof()) {
            throw new ResourceConflictException(__('Payment proof uploads are not enabled for this event'));
        }

        if ($order->getStatus() !== OrderStatus::AWAITING_OFFLINE_PAYMENT->name) {
            throw new ResourceConflictException(__('Order is not awaiting offline payment'));
        }

        return $order;
    }

    private function getOrderForEvent(int $eventId, string $orderShortId): OrderDomainObject
    {
        $order = $this->orderRepository->findByShortId($orderShortId);

        if (! $order || $order->getEventId() !== $eventId) {
            throw new ResourceNotFoundException(__('Order not found'));
        }

        return $order;
    }

    private function getOrderForEventId(int $eventId, int $orderId): OrderDomainObject
    {
        $order = $this->orderRepository->findFirstWhere([
            OrderDomainObjectAbstract::ID => $orderId,
            OrderDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if (! $order instanceof OrderDomainObject) {
            throw new ResourceNotFoundException(__('Order not found'));
        }

        return $order;
    }

    private function getPendingProof(OrderDomainObject $order, int $paymentProofId): OrderPaymentProofDomainObject
    {
        $proof = $this->paymentProofRepository->findPendingForOrder($order->getId());

        if (! $proof || $proof->getId() !== $paymentProofId) {
            throw new ResourceConflictException(__('Payment proof is not awaiting review'));
        }

        return $proof;
    }

    private function getProofForOrder(int $orderId, int $paymentProofId): OrderPaymentProofDomainObject
    {
        $proof = $this->paymentProofRepository->findFirstWhere([
            'id' => $paymentProofId,
            'order_id' => $orderId,
        ]);

        if (! $proof instanceof OrderPaymentProofDomainObject) {
            throw new ResourceNotFoundException(__('Payment proof not found'));
        }

        return $proof;
    }

    private function getEventWithPaymentContext(int $eventId): EventDomainObject
    {
        return $this->eventRepository
            ->loadRelation(new Relationship(\HiEvents\DomainObjects\OrganizerDomainObject::class, name: 'organizer'))
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->findById($eventId);
    }

    private function download(OrderPaymentProofDomainObject $proof): StreamedResponse
    {
        if (! $this->filesystemManager->disk($proof->getDisk())->exists($proof->getPath())) {
            throw new ResourceNotFoundException(__('Payment proof file not found'));
        }

        return $this->filesystemManager
            ->disk($proof->getDisk())
            ->download($proof->getPath(), $proof->getOriginalFilename());
    }
}
