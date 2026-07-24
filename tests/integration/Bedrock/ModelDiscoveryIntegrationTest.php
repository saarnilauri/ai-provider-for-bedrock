<?php

declare(strict_types=1);

namespace AiProviderForBedrock\Tests\Integration\Bedrock;

use AiProviderForBedrock\Provider\ProviderForBedrock;
use AiProviderForBedrock\Tests\Integration\Traits\IntegrationTestTrait;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Providers\ProviderRegistry;

/**
 * Integration tests for Bedrock model discovery.
 *
 * These tests make real API calls to the Amazon Bedrock control-plane
 * inference profiles endpoint.
 *
 * @group integration
 * @group bedrock
 *
 * @coversNothing
 */
class ModelDiscoveryIntegrationTest extends TestCase
{
    use IntegrationTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBedrockCredentials();

        $registry = new ProviderRegistry();
        $registry->registerProvider(ProviderForBedrock::class);
    }

    /**
     * Tests that models are discovered from the inference profiles API.
     */
    public function testListsModelsFromInferenceProfilesApi(): void
    {
        $models = ProviderForBedrock::modelMetadataDirectory()->listModelMetadata();

        $this->assertNotEmpty($models);

        $modelIds = array_map(
            static fn ($model): string => $model->getId(),
            $models
        );

        foreach ($modelIds as $modelId) {
            $this->assertStringContainsString('anthropic.claude', $modelId);
        }

        /*
         * The live API knows models the hardcoded fallback does not; seeing
         * more than the three fallback models proves the fetch happened.
         */
        $this->assertGreaterThan(3, count($models));
    }
}
