<?php

declare(strict_types=1);

namespace CityBus\Services\Payments;

final class CashAdapter implements PaymentAdapterInterface
{
    public function __construct(array $provider) {}

    public function initiate(int $amount, string $msisdn, string $reference): array
    {
        return ['status'=>'confirmed','provider_tx_id'=>'CASH-'.$reference,'message'=>'Encaissement espèces'];
    }
    public function checkStatus(string $providerTxId): string { return 'confirmed'; }
    public function refund(string $providerTxId, int $amount): bool { return true; }
}
