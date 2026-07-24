<?php

declare(strict_types=1);

namespace AiProviderForBedrock\Provider;

use AiProviderForBedrock\Metadata\ProviderForBedrockModelMetadataDirectory;
use AiProviderForBedrock\Models\ProviderForBedrockTextGenerationModel;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
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
        $providerMetadataArgs = [
            'bedrock',
            'Claude on Bedrock',
            ProviderTypeEnum::cloud(),
            'https://console.aws.amazon.com/bedrock/home#/api-keys',
            /*
             * Declaring API key authentication makes the provider manageable via
             * the WordPress core connectors settings page, and lets the registry
             * pick up a BEDROCK_API_KEY environment variable or constant.
             * Bedrock API keys are sent as Bearer tokens, so the generic
             * ApiKeyRequestAuthentication works as-is.
             */
            RequestAuthenticationMethod::apiKey()
        ];
        // Provider description support was added in 1.2.0.
        if (version_compare(AiClient::VERSION, '1.2.0', '>=')) {
            // For WordPress, we should translate the description.
            if (function_exists('__')) {
                $providerMetadataArgs[] = __('Text generation with Claude on Bedrock.', 'ai-provider-for-bedrock');
            } else {
                $providerMetadataArgs[] = 'Text generation with Claude on Bedrock.';
            }
        }
        // Provider logoPath support was added in 1.3.0.
        if (version_compare(AiClient::VERSION, '1.3.0', '>=')) {
            $providerMetadataArgs[] = dirname(__DIR__, 2) . '/assets/images/bedrock.svg';
        }
        return new ProviderMetadata(...$providerMetadataArgs);
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
