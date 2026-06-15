<?php

declare(strict_types=1);

namespace CityBus\Services\Payments;

/**
 * Cinetpay — agrégateur multi (carte, MoMo, wallet) très utilisé en CEMAC.
 * Doc : https://docs.cinetpay.com/
 */
final class CinetpayAdapter extends AbstractMomoAdapter
{
    protected function callRealApi(int $amount, string $msisdn, string $reference): array
    {
        return ['status'=>'failed','provider_tx_id'=>null,'message'=>'Cinetpay API not configured'];
    }
    protected function checkStatusReal(string $providerTxId): string { return 'pending'; }
}
