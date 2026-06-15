<?php

declare(strict_types=1);

namespace CityBus\Services\Payments;

final class OrangeMoneyAdapter extends AbstractMomoAdapter
{
    protected function callRealApi(int $amount, string $msisdn, string $reference): array
    {
        return ['status'=>'failed','provider_tx_id'=>null,'message'=>'Orange Money API not configured'];
    }
    protected function checkStatusReal(string $providerTxId): string { return 'pending'; }
}
