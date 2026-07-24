<?php

declare(strict_types=1);

namespace AiProviderForBedrock\Tests\Unit\Models;

use AiProviderForBedrock\Models\ProviderForBedrockTextGenerationModel;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Mock class for testing ProviderForBedrockTextGenerationModel.
 */
class MockProviderForBedrockTextGenerationModel extends ProviderForBedrockTextGenerationModel
{
    private RequestAuthenticationInterface $testRequestAuthentication;

    /**
     * Constructor.
     *
     * @param ModelMetadata $metadata
     * @param ProviderMetadata $providerMetadata
     * @param HttpTransporterInterface $httpTransporter
     * @param RequestAuthenticationInterface $requestAuthentication
     */
    public function __construct(
        ModelMetadata $metadata,
        ProviderMetadata $providerMetadata,
        HttpTransporterInterface $httpTransporter,
        RequestAuthenticationInterface $requestAuthentication
    ) {
        parent::__construct($metadata, $providerMetadata);

        $this->setHttpTransporter($httpTransporter);
        $this->testRequestAuthentication = $requestAuthentication;
    }

    /**
     * Overrides getRequestAuthentication to return the injected mock
     * instead of calling BedrockRequestAuthentication::fromSettings().
     */
    public function getRequestAuthentication(): RequestAuthenticationInterface
    {
        return $this->testRequestAuthentication;
    }

    /**
     * Exposes prepareGenerateTextParams for testing.
     *
     * @param list<Message> $prompt
     * @return array<string, mixed>
     */
    public function exposePrepareGenerateTextParams(array $prompt): array
    {
        return $this->prepareGenerateTextParams($prompt);
    }
}
