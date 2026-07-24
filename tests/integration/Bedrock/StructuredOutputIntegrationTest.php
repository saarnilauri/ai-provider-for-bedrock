<?php

declare(strict_types=1);

namespace AiProviderForBedrock\Tests\Integration\Bedrock;

use AiProviderForBedrock\Provider\ProviderForBedrock;
use AiProviderForBedrock\Tests\Integration\Traits\IntegrationTestTrait;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\ProviderRegistry;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;

/**
 * Integration tests for Bedrock Claude structured output.
 *
 * These tests make real API calls to Amazon Bedrock and require
 * BEDROCK_ACCESS_KEY_ID, BEDROCK_SECRET_ACCESS_KEY, and BEDROCK_REGION
 * environment variables to be set.
 *
 * @group integration
 * @group bedrock
 * @group structured-output
 *
 * @coversNothing
 */
class StructuredOutputIntegrationTest extends TestCase
{
    use IntegrationTestTrait;

    private ProviderRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBedrockCredentials();

        $this->registry = new ProviderRegistry();
        $this->registry->registerProvider(ProviderForBedrock::class);
    }

    /**
     * Tests structured output with an explicit JSON schema.
     */
    public function testStructuredOutputWithJsonSchema(): void
    {
        $schema = [
            'type'                 => 'object',
            'properties'           => [
                'city'       => [
                    'type'        => 'string',
                    'description' => 'Name of the city',
                ],
                'country'    => [
                    'type'        => 'string',
                    'description' => 'Name of the country',
                ],
                'population' => [
                    'type'        => 'integer',
                    'description' => 'Approximate population of the city',
                ],
            ],
            'required'             => ['city', 'country', 'population'],
            'additionalProperties' => false,
        ];

        $result = AiClient::prompt(
            'Provide information about Paris, France. Use an approximate population of 2000000.',
            $this->registry
        )
            ->usingProvider('bedrock')
            ->asJsonResponse($schema)
            ->generateTextResult();

        $this->assertInstanceOf(GenerativeAiResult::class, $result);

        $text = $result->toText();
        $this->assertNotEmpty($text);

        $decoded = json_decode($text, true);
        $this->assertIsArray($decoded, 'Response should be valid JSON. Got: ' . $text);

        $this->assertArrayHasKey('city', $decoded);
        $this->assertArrayHasKey('country', $decoded);
        $this->assertArrayHasKey('population', $decoded);

        $this->assertIsString($decoded['city']);
        $this->assertIsString($decoded['country']);
        $this->assertIsInt($decoded['population']);

        $this->assertStringContainsStringIgnoringCase('paris', $decoded['city']);
        $this->assertStringContainsStringIgnoringCase('france', $decoded['country']);
    }
}
