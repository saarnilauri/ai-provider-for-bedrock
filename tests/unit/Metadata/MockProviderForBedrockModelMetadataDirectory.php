<?php

declare(strict_types=1);

namespace AiProviderForBedrock\Tests\Unit\Metadata;

use AiProviderForBedrock\Metadata\ProviderForBedrockModelMetadataDirectory;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Mock class for testing ProviderForBedrockModelMetadataDirectory.
 */
class MockProviderForBedrockModelMetadataDirectory extends ProviderForBedrockModelMetadataDirectory
{
    /**
     * Exposes sendListModelsRequest for testing.
     *
     * @return array<string, ModelMetadata>
     */
    public function exposeSendListModelsRequest(): array
    {
        return $this->sendListModelsRequest();
    }

    /**
     * Exposes parseInferenceProfilesResponse for testing.
     *
     * @return array<string, string>
     */
    public function exposeParseInferenceProfilesResponse(Response $response): array
    {
        return $this->parseInferenceProfilesResponse($response);
    }
}
