<?php

declare(strict_types=1);

namespace AiProviderForBedrock\Tests\Integration\Bedrock;

use AiProviderForBedrock\Provider\ProviderForBedrock;
use AiProviderForBedrock\Tests\Integration\Traits\IntegrationTestTrait;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\TokenLimitReachedException;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Providers\ProviderRegistry;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;

/**
 * Integration tests for Bedrock Claude text generation.
 *
 * These tests make real API calls to Amazon Bedrock and require
 * BEDROCK_ACCESS_KEY_ID, BEDROCK_SECRET_ACCESS_KEY, and BEDROCK_REGION
 * environment variables to be set.
 *
 * @group integration
 * @group bedrock
 *
 * @coversNothing
 */
class TextGenerationIntegrationTest extends TestCase
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
     * Tests basic text generation with a simple prompt.
     */
    public function testSimpleTextGeneration(): void
    {
        $result = AiClient::prompt('Say "hello" and nothing else.', $this->registry)
            ->usingProvider('bedrock')
            ->generateTextResult();

        $this->assertInstanceOf(GenerativeAiResult::class, $result);
        $this->assertStringContainsStringIgnoringCase('hello', $result->toText());
    }

    /**
     * Tests text generation with a simple multi-turn chat.
     */
    public function testMultiTurnTextGeneration(): void
    {
        $result = AiClient::prompt([
            Message::fromArray([
                'role' => 'user',
                'parts' => [['text' => 'When was WordPress first released?']],
            ]),
            Message::fromArray([
                'role' => 'model',
                'parts' => [['text' => 'In 2003.']],
            ]),
            Message::fromArray([
                'role' => 'user',
                'parts' => [['text' => 'Who created it?']],
            ]),
        ], $this->registry)
            ->usingProvider('bedrock')
            ->generateTextResult();

        $this->assertInstanceOf(GenerativeAiResult::class, $result);
        $this->assertStringContainsStringIgnoringCase('Matt Mullenweg', $result->toText());
    }

    /**
     * Tests that generateTextResult() throws TokenLimitReachedException when max_tokens is exceeded.
     */
    public function testTextGenerationThrowsOnTokenLimitReached(): void
    {
        $this->expectException(TokenLimitReachedException::class);

        AiClient::prompt('Count from 1 to 1000, writing each number on its own line.', $this->registry)
            ->usingProvider('bedrock')
            ->usingMaxTokens(5)
            ->generateTextResult();
    }

    /**
     * Tests that text generation returns token usage information.
     */
    public function testTextGenerationReturnsTokenUsage(): void
    {
        $result = AiClient::prompt('Say "hello" and nothing else.', $this->registry)
            ->usingProvider('bedrock')
            ->generateTextResult();

        $tokenUsage = $result->getTokenUsage();
        $this->assertGreaterThan(0, $tokenUsage->getPromptTokens());
        $this->assertGreaterThan(0, $tokenUsage->getCompletionTokens());
        $this->assertGreaterThan(0, $tokenUsage->getTotalTokens());
    }
}
