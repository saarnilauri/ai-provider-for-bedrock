<?php

declare(strict_types=1);

namespace AiProviderForBedrock\Provider;

use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;

/**
 * Availability check for the Amazon Bedrock provider.
 *
 * Checks whether AWS credentials are configured without making API calls.
 *
 * @since 1.0.0
 */
class BedrockProviderAvailability implements ProviderAvailabilityInterface
{
    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function isConfigured(): bool
    {
        $accessKey = \AiProviderForBedrock\get_bedrock_access_key_id();
        $secretKey = \AiProviderForBedrock\get_bedrock_secret_access_key();

        return $accessKey !== '' && $secretKey !== '';
    }
}
