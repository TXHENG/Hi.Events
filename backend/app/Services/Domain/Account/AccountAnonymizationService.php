<?php

namespace HiEvents\Services\Domain\Account;

use HiEvents\Services\Domain\Account\Anonymization\AccountDataResolver;
use HiEvents\Services\Domain\Account\Anonymization\Anonymizers\AccountAnonymizer;
use HiEvents\Services\Domain\Account\Anonymization\Anonymizers\ActivityLogAnonymizer;
use HiEvents\Services\Domain\Account\Anonymization\Anonymizers\EventContentAnonymizer;
use HiEvents\Services\Domain\Account\Anonymization\Anonymizers\ImageAnonymizer;
use HiEvents\Services\Domain\Account\Anonymization\Anonymizers\OrderAnonymizer;
use HiEvents\Services\Domain\Account\Anonymization\Anonymizers\OrganizerAnonymizer;
use HiEvents\Services\Domain\Account\Anonymization\Anonymizers\PartnerAnonymizer;
use HiEvents\Services\Domain\Account\Anonymization\Anonymizers\UserAnonymizer;
use HiEvents\Services\Domain\Account\Anonymization\EntityAnonymizationResult;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Storage;
use Psr\Log\LoggerInterface;
use Throwable;

class AccountAnonymizationService
{
    public function __construct(
        private readonly AccountDataResolver $dataResolver,
        private readonly OrderAnonymizer $orderAnonymizer,
        private readonly EventContentAnonymizer $eventContentAnonymizer,
        private readonly ActivityLogAnonymizer $activityLogAnonymizer,
        private readonly PartnerAnonymizer $partnerAnonymizer,
        private readonly UserAnonymizer $userAnonymizer,
        private readonly OrganizerAnonymizer $organizerAnonymizer,
        private readonly ImageAnonymizer $imageAnonymizer,
        private readonly AccountAnonymizer $accountAnonymizer,
        private readonly DatabaseManager $databaseManager,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws Throwable
     */
    public function anonymizeAccount(int $accountId): array
    {
        $context = $this->dataResolver->resolve($accountId);

        $this->logger->info('Anonymizing account', [
            'account_id' => $accountId,
            'stripe_account_ids' => $context->stripeAccountIds,
        ]);

        $results = $this->databaseManager->transaction(function () use ($context) {
            $anonymizers = [
                $this->orderAnonymizer,
                $this->eventContentAnonymizer,
                $this->activityLogAnonymizer,
                $this->partnerAnonymizer,
                $this->userAnonymizer,
                $this->organizerAnonymizer,
                $this->imageAnonymizer,
                $this->accountAnonymizer,
            ];

            return collect($anonymizers)
                ->flatMap(fn ($anonymizer) => $anonymizer->anonymize($context))
                ->values();
        });

        $this->deleteFiles([...$context->imageFiles, ...$context->paymentProofFiles]);

        $manifest = $results
            ->map(fn (EntityAnonymizationResult $result) => $result->toArray())
            ->all();

        $this->logger->info('Account anonymized', [
            'account_id' => $accountId,
            'manifest' => $manifest,
        ]);

        return $manifest;
    }

    private function deleteFiles(array $files): void
    {
        foreach ($files as $file) {
            try {
                Storage::disk($file['disk'])->delete($file['path']);
            } catch (Throwable $exception) {
                $this->logger->warning('Failed to delete file during account anonymization', [
                    'disk' => $file['disk'],
                    'path' => $file['path'],
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
