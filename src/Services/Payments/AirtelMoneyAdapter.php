<?php

declare(strict_types=1);

namespace CityBus\Services\Payments;

/**
 * Airtel Money (CG, GA, ...) — API REST OAuth2.
 * Doc : https://developers.airtel.africa/
 *
 * En prod : configurer api_key/api_secret dans payment_providers, désactiver sandbox.
 */
final class AirtelMoneyAdapter extends AbstractMomoAdapter
{
    protected function callRealApi(int $amount, string $msisdn, string $reference): array
    {
        // TODO en prod : POST /merchant/v1/payments/ avec Bearer token
        // $token = $this->getOAuthToken();
        // $resp = $this->httpPost($this->provider['api_endpoint'].'/merchant/v1/payments/', [...], ['Authorization: Bearer '.$token]);
        return ['status'=>'failed','provider_tx_id'=>null,'message'=>'Airtel API not configured'];
    }

    protected function checkStatusReal(string $providerTxId): string
    {
        // GET /standard/v1/payments/{tx_id}
        return 'pending';
    }
}
