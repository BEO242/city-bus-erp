<?php

declare(strict_types=1);

namespace CityBus\Services\Payments;

final class PawapayAdapter extends AbstractMomoAdapter
{
    protected function callRealApi(int $amount, string $msisdn, string $reference): array
    {
        return ['status'=>'failed','provider_tx_id'=>null,'message'=>'Pawapay API not configured'];
    }
    protected function checkStatusReal(string $providerTxId): string { return 'pending'; }
}
