<?php

declare(strict_types=1);

namespace AiProviderForBedrock\Provider;

use AiProviderForBedrock\Metadata\ProviderForBedrockModelMetadataDirectory;
use AiProviderForBedrock\Models\ProviderForBedrockTextGenerationModel;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Class for the Amazon Bedrock Claude provider.
 *
 * @since 1.0.0
 */
class ProviderForBedrock extends AbstractApiProvider
{
    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function baseUrl(): string
    {
        return 'https://bedrock-runtime.' . \AiProviderForBedrock\get_bedrock_region() . '.amazonaws.com';
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createModel(
        ModelMetadata $modelMetadata,
        ProviderMetadata $providerMetadata
    ): ModelInterface {
        $capabilities = $modelMetadata->getSupportedCapabilities();
        foreach ($capabilities as $capability) {
            if ($capability->isTextGeneration()) {
                return new ProviderForBedrockTextGenerationModel($modelMetadata, $providerMetadata);
            }
        }

        throw new RuntimeException(
            'Unsupported model capabilities: ' . implode(', ', $capabilities)
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createProviderMetadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            'bedrock',
            'Claude on Bedrock',
            ProviderTypeEnum::cloud(),
            'https://console.aws.amazon.com/bedrock/',
            null
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createProviderAvailability(): ProviderAvailabilityInterface
    {
        return new BedrockProviderAvailability();
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface
    {
        return new ProviderForBedrockModelMetadataDirectory();
    }
}
