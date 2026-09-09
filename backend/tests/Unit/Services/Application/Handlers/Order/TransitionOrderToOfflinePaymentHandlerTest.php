<?php

namespace Tests\Unit\Services\Application\Handlers\Order;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrderPaymentProofDomainObject;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\TransitionOrderToOfflinePaymentPublicDTO;
use HiEvents\Services\Application\Handlers\Order\TransitionOrderToOfflinePaymentHandler;
use HiEvents\Services\Domain\Order\OccurrenceStatusValidator;
use HiEvents\Services\Domain\Order\OrderPaymentProofService;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class TransitionOrderToOfflinePaymentHandlerTest extends TestCase
{
    private const EVENT_ID = 10;

    private const ORDER_ID = 20;

    private OrderRepositoryInterface|MockInterface $orderRepository;

    private DatabaseManager|MockInterface $databaseManager;

    private EventSettingsRepositoryInterface|MockInterface $eventSettingsRepository;

    private OccurrenceStatusValidator|MockInterface $occurrenceStatusValidator;

    private ProductQuantityUpdateService|MockInterface $productQuantityUpdateService;

    private CheckoutSessionManagementService|MockInterface $sessionManagementService;

    private OrderPaymentProofService|MockInterface $paymentProofService;

    private DomainEventDispatcherService|MockInterface $domainEventDispatcherService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);
        $this->eventSettingsRepository = Mockery::mock(EventSettingsRepositoryInterface::class);
        $this->occurrenceStatusValidator = Mockery::mock(OccurrenceStatusValidator::class);
        $this->productQuantityUpdateService = Mockery::mock(ProductQuantityUpdateService::class);
        $this->sessionManagementService = Mockery::mock(CheckoutSessionManagementService::class);
        $this->paymentProofService = Mockery::mock(OrderPaymentProofService::class);
        $this->domainEventDispatcherService = Mockery::mock(DomainEventDispatcherService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_requires_a_proof_when_the_event_setting_is_enabled(): void
    {
        $this->expectException(ValidationException::class);

        $this->handler()->validateOfflinePayment(
            order: $this->order(),
            settings: $this->settings(proofRequired: true),
            dto: $this->dto(),
        );
    }

    public function test_rejects_an_order_that_does_not_belong_to_the_route_event(): void
    {
        $this->databaseManager->shouldReceive('transaction')->once()->andReturnUsing(
            fn (callable $callback) => $callback(),
        );
        $this->orderRepository->shouldReceive('loadRelation')->once()->with(OrderItemDomainObject::class)->andReturnSelf();
        $this->orderRepository->shouldReceive('findByShortIdForUpdate')->once()->with('ORDER-123')->andReturn(
            $this->order(eventId: self::EVENT_ID + 1),
        );

        $this->expectException(ResourceConflictException::class);

        $this->handler()->handle($this->dto());
    }

    public function test_discards_a_stored_proof_when_the_transition_fails(): void
    {
        $order = $this->order();
        $proof = (new OrderPaymentProofDomainObject)
            ->setId(30)
            ->setDisk('private')
            ->setPath('payment-proofs/20/receipt.png');
        $dto = $this->dto(UploadedFile::fake()->image('receipt.png'));

        $this->databaseManager->shouldReceive('transaction')->once()->andReturnUsing(
            fn (callable $callback) => $callback(),
        );
        $this->orderRepository->shouldReceive('loadRelation')->once()->with(OrderItemDomainObject::class)->andReturnSelf();
        $this->orderRepository->shouldReceive('findByShortIdForUpdate')->once()->with('ORDER-123')->andReturn($order);
        $this->eventSettingsRepository->shouldReceive('findFirstWhere')->once()->with(['event_id' => self::EVENT_ID])->andReturn(
            $this->settings(proofRequired: true),
        );
        $this->sessionManagementService->shouldReceive('verifySession')->once()->with('checkout-session')->andReturnTrue();
        $this->occurrenceStatusValidator->shouldReceive('assertOrderOccurrencesArePurchasable')->once()->with($order);
        $this->paymentProofService->shouldReceive('storePendingProof')->once()->with(
            $order,
            self::EVENT_ID,
            $dto->proof,
            'BANK-TRANSFER-123',
        )->andReturn($proof);
        $this->productQuantityUpdateService->shouldReceive('updateQuantitiesFromOrder')->once()->with($order)->andThrow(
            new RuntimeException('Could not reserve ticket quantities'),
        );
        $this->paymentProofService->shouldReceive('discardStoredProof')->once()->with($proof);
        $this->paymentProofService->shouldNotReceive('notifySubmission');

        $this->expectException(RuntimeException::class);

        $this->handler()->handle($dto);
    }

    public function test_queues_notifications_after_the_transition_commits(): void
    {
        Event::fake();
        $order = $this->order();
        $updatedOrder = $this->order();
        $proof = (new OrderPaymentProofDomainObject)
            ->setId(30)
            ->setDisk('private')
            ->setPath('payment-proofs/20/receipt.png');
        $dto = $this->dto(UploadedFile::fake()->image('receipt.png'));
        $transactionCommitted = false;

        $this->databaseManager->shouldReceive('transaction')->once()->andReturnUsing(
            function (callable $callback) use (&$transactionCommitted) {
                $result = $callback();
                $transactionCommitted = true;

                return $result;
            },
        );
        $this->orderRepository->shouldReceive('loadRelation')->twice()->with(OrderItemDomainObject::class)->andReturnSelf();
        $this->orderRepository->shouldReceive('findByShortIdForUpdate')->once()->with('ORDER-123')->andReturn($order);
        $this->orderRepository->shouldReceive('updateFromArray')->once()->with(self::ORDER_ID, [
            'payment_status' => OrderPaymentStatus::AWAITING_OFFLINE_PAYMENT->name,
            'status' => OrderStatus::AWAITING_OFFLINE_PAYMENT->name,
            'payment_provider' => PaymentProviders::OFFLINE->value,
        ]);
        $this->orderRepository->shouldReceive('findById')->once()->with(self::ORDER_ID)->andReturn($updatedOrder);
        $this->eventSettingsRepository->shouldReceive('findFirstWhere')->once()->with(['event_id' => self::EVENT_ID])->andReturn(
            $this->settings(proofRequired: true),
        );
        $this->sessionManagementService->shouldReceive('verifySession')->once()->with('checkout-session')->andReturnTrue();
        $this->occurrenceStatusValidator->shouldReceive('assertOrderOccurrencesArePurchasable')->once()->with($order);
        $this->paymentProofService->shouldReceive('storePendingProof')->once()->with(
            $order,
            self::EVENT_ID,
            $dto->proof,
            'BANK-TRANSFER-123',
        )->andReturn($proof);
        $this->productQuantityUpdateService->shouldReceive('updateQuantitiesFromOrder')->once()->with($order);
        $this->domainEventDispatcherService->shouldReceive('dispatch')->once();
        $this->paymentProofService->shouldReceive('notifySubmission')->once()->with(
            self::EVENT_ID,
            $updatedOrder,
        )->andReturnUsing(function () use (&$transactionCommitted) {
            $this->assertTrue($transactionCommitted);
        });

        $this->assertSame($updatedOrder, $this->handler()->handle($dto));
        Event::assertDispatched(OrderStatusChangedEvent::class);
    }

    private function handler(): TransitionOrderToOfflinePaymentHandler
    {
        return new TransitionOrderToOfflinePaymentHandler(
            productQuantityUpdateService: $this->productQuantityUpdateService,
            orderRepository: $this->orderRepository,
            databaseManager: $this->databaseManager,
            eventSettingsRepository: $this->eventSettingsRepository,
            occurrenceStatusValidator: $this->occurrenceStatusValidator,
            domainEventDispatcherService: $this->domainEventDispatcherService,
            sessionManagementService: $this->sessionManagementService,
            paymentProofService: $this->paymentProofService,
        );
    }

    private function dto(?UploadedFile $proof = null): TransitionOrderToOfflinePaymentPublicDTO
    {
        return new TransitionOrderToOfflinePaymentPublicDTO(
            eventId: self::EVENT_ID,
            orderShortId: 'ORDER-123',
            proof: $proof,
            paymentReference: 'BANK-TRANSFER-123',
        );
    }

    private function order(int $eventId = self::EVENT_ID): OrderDomainObject
    {
        return (new OrderDomainObject)
            ->setId(self::ORDER_ID)
            ->setEventId($eventId)
            ->setStatus(OrderStatus::RESERVED->name)
            ->setSessionId('checkout-session')
            ->setReservedUntil(Carbon::now()->addHour()->toDateTimeString());
    }

    private function settings(bool $proofRequired): EventSettingDomainObject
    {
        return (new EventSettingDomainObject)
            ->setEventId(self::EVENT_ID)
            ->setPaymentProviders([PaymentProviders::OFFLINE->value])
            ->setAllowOfflinePaymentProof($proofRequired)
            ->setEnableInvoicing(false);
    }
}
