<?php

declare(strict_types=1);

namespace CityBus\Services\Payments;

use CityBus\Core\StructuredLogger;

/**
 * Base abstraite Mobile Money. En sandbox, simule une transaction.
 * En prod : surcharger initiate() pour appeler la vraie API HTTP du provider.
 */
abstract class AbstractMomoAdapter implements PaymentAdapterInterface
{
    protected array $provider;

    public function __construct(array $provider) { $this->provider = $provider; }

    public function initiate(int $amount, string $msisdn, string $reference): array
    {
        if ((int)$this->provider['sandbox_mode'] === 1) {
            $tx = strtoupper($this->provider['code']) . '-SBX-' . bin2hex(random_bytes(6));
            StructuredLogger::info('payment.sandbox', ['provider'=>$this->provider['code'],'tx'=>$tx,'amount'=>$amount], 'payments');
            return ['status'=>'pending','provider_tx_id'=>$tx,'message'=>'Sandbox: USSD push simulé envoyé au '.$msisdn];
        }

        // Production : appeler vraie API
        return $this->callRealApi($amount, $msisdn, $reference);
    }

    abstract protected function callRealApi(int $amount, string $msisdn, string $reference): array;

    public function checkStatus(string $providerTxId): string
    {
        if ((int)$this->provider['sandbox_mode'] === 1) {
            // Sandbox : simule confirmé après 5 secondes
            return strpos($providerTxId, '-SBX-') !== false ? 'confirmed' : 'pending';
        }
        return $this->checkStatusReal($providerTxId);
    }

    abstract protected function checkStatusReal(string $providerTxId): string;

    public function refund(string $providerTxId, int $amount): bool
    {
        if ((int)$this->provider['sandbox_mode'] === 1) return true;
        return $this->refundReal($providerTxId, $amount);
    }

    protected function refundReal(string $providerTxId, int $amount): bool
    {
        return false; // À implémenter par provider
    }
}
