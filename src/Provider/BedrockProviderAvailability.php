<?php

declare(strict_types=1);

namespace AiProviderForBedrock\Provider;

use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithRequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\Traits\WithRequestAuthenticationTrait;

/**
 * Availability check for the Amazon Bedrock provider.
 *
 * Checks whether credentials are configured without making API calls.
 * Considers both authentication injected by the registry (e.g. an API key
 * stored via the WordPress core connectors settings page) and the plugin's
 * own settings (API key, or an IAM access key pair).
 *
 * @since 1.0.0
 */
class BedrockProviderAvailability implements ProviderAvailabilityInterface, WithRequestAuthenticationInterface
{
    use WithRequestAuthenticationTrait;

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function isConfigured(): bool
    {
        try {
            $requestAuthentication = $this->getRequestAuthentication();
        } catch (RuntimeException $e) {
            $requestAuthentication = null;
        }

        if ($requestAuthentication instanceof ApiKeyRequestAuthentication) {
            if ($requestAuthentication->getApiKey() !== '') {
                return true;
            }
        } elseif ($requestAuthentication !== null) {
            // A Bedrock-specific authentication (e.g. SigV4) has been set on the registry.
            return true;
        }

        if (\AiProviderForBedrock\get_bedrock_api_key() !== '') {
            return true;
        }

        $accessKey = \AiProviderForBedrock\get_bedrock_access_key_id();
        $secretKey = \AiProviderForBedrock\get_bedrock_secret_access_key();

        return $accessKey !== '' && $secretKey !== '';
    }
}
