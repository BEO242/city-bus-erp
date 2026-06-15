<?php

declare(strict_types=1);

namespace CityBus\Services\Payments;

use CityBus\Core\Database;
use CityBus\Core\StructuredLogger;

/**
 * Payment gateway router : choisit le bon adapter selon provider code.
 * Tous les adapters implémentent PaymentAdapterInterface.
 *
 * Vraies clés API à fournir via app_settings (encryptées).
 * Stubs en sandbox renvoient une réponse simulée.
 */
final class PaymentGatewayService
{
    private array $adapters = [];

    public function adapter(string $providerCode): PaymentAdapterInterface
    {
        if (isset($this->adapters[$providerCode])) return $this->adapters[$providerCode];
        $provider = Database::selectOne("SELECT * FROM payment_providers WHERE code = ? AND active = 1", [$providerCode]);
        if (!$provider) throw new \RuntimeException("Provider $providerCode introuvable ou désactivé");
        $class = match($providerCode) {
            'AIRTEL_MONEY' => AirtelMoneyAdapter::class,
            'MTN_MOMO'     => MtnMomoAdapter::class,
            'ORANGE_MONEY' => OrangeMoneyAdapter::class,
            'CINETPAY'     => CinetpayAdapter::class,
            'PAWAPAY'      => PawapayAdapter::class,
            default        => CashAdapter::class,
        };
        return $this->adapters[$providerCode] = new $class($provider);
    }

    public function initiate(string $providerCode, int $amount, string $msisdn, string $reference): array
    {
        $adapter = $this->adapter($providerCode);
        StructuredLogger::info('payment.initiate', compact('providerCode','amount','reference'), 'payments');
        return $adapter->initiate($amount, $msisdn, $reference);
    }

    public function checkStatus(string $providerCode, string $providerTxId): string
    {
        return $this->adapter($providerCode)->checkStatus($providerTxId);
    }

    public function recordPayment(int $invoiceId, string $providerCode, int $amount, ?string $providerTxId, string $status, array $extra = []): int
    {
        $provider = Database::selectOne("SELECT id, fee_pct, fee_fixed FROM payment_providers WHERE code = ?", [$providerCode]);
        $fee = (int)round($amount * (float)$provider['fee_pct'] / 100) + (int)$provider['fee_fixed'];

        $id = Database::insert('payments', [
            'invoice_id'    => $invoiceId,
            'amount'        => $amount,
            'payment_method'=> $providerCode,
            'provider_id'   => $provider['id'] ?? null,
            'provider_transaction_id' => $providerTxId,
            'provider_status' => $status,
            'provider_fee'  => $fee,
            'paid_at'       => $status === 'confirmed' ? date('Y-m-d H:i:s') : null,
        ]);

        if ($status === 'confirmed') {
            (new \CityBus\Services\InvoiceService())->markPaid($invoiceId, $amount);
            (new \CityBus\Services\AccountingV4Service())->postPayment($invoiceId, $amount, $providerCode === 'CASH' ? 'cash' : 'mobile_money');
        }
        return $id;
    }
}
