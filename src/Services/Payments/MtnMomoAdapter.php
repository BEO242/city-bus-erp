<?php

declare(strict_types=1);

namespace CityBus\Services\Payments;

/**
 * MTN Mobile Money — Open API.
 * Doc : https://momodeveloper.mtn.com/
 *
 * Endpoints : /collection/v1_0/requesttopay puis polling /collection/v1_0/requesttopay/{X-Reference-Id}
 */
final class MtnMomoAdapter extends AbstractMomoAdapter
{
    protected function callRealApi(int $amount, string $msisdn, string $reference): array
    {
        // TODO en prod
        return ['status'=>'failed','provider_tx_id'=>null,'message'=>'MTN MoMo API not configured'];
    }

    protected function checkStatusReal(string $providerTxId): string
    {
        return 'pending';
    }
}
