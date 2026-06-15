<?php

declare(strict_types=1);

namespace CityBus\Services\Payments;

interface PaymentAdapterInterface
{
    /** @return array{status:string, provider_tx_id:?string, message:string} */
    public function initiate(int $amount, string $msisdn, string $reference): array;

    public function checkStatus(string $providerTxId): string;

    public function refund(string $providerTxId, int $amount): bool;
}
