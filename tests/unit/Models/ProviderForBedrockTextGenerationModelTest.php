<?php

declare(strict_types=1);

namespace AiProviderForBedrock\Tests\Unit\Models;

use AiProviderForBedrock\Authentication\BedrockRequestAuthentication;
use AiProviderForBedrock\Models\ProviderForBedrockTextGenerationModel;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Common\Exception\TokenLimitReachedException;
use WordPress\AiClient\Providers\Http\Exception\ClientException;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;

/**
 * @covers \AiProviderForBedrock\Models\ProviderForBedrockTextGenerationModel
 */
class ProviderForBedrockTextGenerationModelTest extends TestCase
{
    /**
     * @var ModelMetadata&\PHPUnit\Framework\MockObject\MockObject
     */
    private $modelMetadata;

    /**
     * @var ProviderMetadata&\PHPUnit\Framework\MockObject\MockObject
     */
    private $providerMetadata;

    /**
     * @var HttpTransporterInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $mockHttpTransporter;

    /**
     * @var RequestAuthenticationInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $mockRequestAuthentication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modelMetadata = $this->createStub(ModelMetadata::class);
        $this->modelMetadata->method('getId')->willReturn('anthropic.claude-sonnet-4-6');
        $this->providerMetadata = $this->createStub(ProviderMetadata::class);
        $this->providerMetadata->method('getName')->willReturn('Claude on Bedrock');
        $this->mockHttpTransporter = $this->createMock(HttpTransporterInterface::class);
        $this->mockRequestAuthentication = $this->createMock(RequestAuthenticationInterface::class);
    }

    /**
     * Creates a mock instance of ProviderForBedrockTextGenerationModel.
     */
    private function createModel(?ModelConfig $modelConfig = null): MockProviderForBedrockTextGenerationModel
    {
        $model = new MockProviderForBedrockTextGenerationModel(
            $this->modelMetadata,
            $this->providerMetadata,
            $this->mockHttpTransporter,
            $this->mockRequestAuthentication
        );

        if ($modelConfig) {
            $model->setConfig($modelConfig);
        }

        return $model;
    }

    /**
     * Tests that registry-injected API key authentication takes precedence.
     */
    public function testGetRequestAuthenticationPrefersInjectedApiKey(): void
    {
        $model = new ProviderForBedrockTextGenerationModel($this->modelMetadata, $this->providerMetadata);
        $injected = new ApiKeyRequestAuthentication('connector-stored-key');
        $model->setRequestAuthentication($injected);

        $auth = $model->getRequestAuthentication();

        $this->assertInstanceOf(ApiKeyRequestAuthentication::class, $auth);
        $this->assertSame('connector-stored-key', $auth->getApiKey());
    }

    /**
     * Tests that plugin settings are used when nothing is injected by the registry.
     */
    public function testGetRequestAuthenticationFallsBackToSettings(): void
    {
        $model = new ProviderForBedrockTextGenerationModel($this->modelMetadata, $this->providerMetadata);

        // The unit test stubs provide an IAM key pair and no API key, so the
        // fallback resolves to SigV4 authentication.
        $auth = $model->getRequestAuthentication();

        $this->assertInstanceOf(BedrockRequestAuthentication::class, $auth);
    }

    /**
     * Tests that an injected API key authentication with an empty key is ignored.
     */
    public function testGetRequestAuthenticationIgnoresEmptyInjectedApiKey(): void
    {
        $model = new ProviderForBedrockTextGenerationModel($this->modelMetadata, $this->providerMetadata);
        $model->setRequestAuthentication(new ApiKeyRequestAuthentication(''));

        $auth = $model->getRequestAuthentication();

        $this->assertInstanceOf(BedrockRequestAuthentication::class, $auth);
    }

    /**
     * Tests generateTextResult() method on success.
     */
    public function testGenerateTextResultSuccess(): void
    {
        $prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hello')])];
        $response = new Response(
            200,
            [],
            json_encode([
                'id' => 'msg_123',
                'role' => 'assistant',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Hi there!',
                    ],
                ],
                'stop_reason' => 'end_turn',
                'usage' => [
                    'input_tokens' => 10,
                    'output_tokens' => 5,
                ],
            ])
        );

        $this->mockRequestAuthentication
            ->expects($this->once())
            ->method('authenticateRequest')
            ->willReturnArgument(0);

        $this->mockHttpTransporter
            ->expects($this->once())
            ->method('send')
            ->willReturn($response);

        $model = $this->createModel();
        $result = $model->generateTextResult($prompt);

        $this->assertInstanceOf(GenerativeAiResult::class, $result);
        $this->assertSame('msg_123', $result->getId());
        $this->assertCount(1, $result->getCandidates());
        $this->assertSame('Hi there!', $result->getCandidates()[0]->getMessage()->getParts()[0]->getText());
        $this->assertEquals(FinishReasonEnum::stop(), $result->getCandidates()[0]->getFinishReason());
        $this->assertSame(10, $result->getTokenUsage()->getPromptTokens());
        $this->assertSame(5, $result->getTokenUsage()->getCompletionTokens());
        $this->assertSame(15, $result->getTokenUsage()->getTotalTokens());
    }

    /**
     * Tests generateTextResult() method on API failure.
     */
    public function testGenerateTextResultApiFailure(): void
    {
        $prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hello')])];
        $response = new Response(401, [], '{"message": "The security token included in the request is invalid."}');

        $this->mockRequestAuthentication
            ->expects($this->once())
            ->method('authenticateRequest')
            ->willReturnArgument(0);

        $this->mockHttpTransporter
            ->expects($this->once())
            ->method('send')
            ->willReturn($response);

        $model = $this->createModel();

        $this->expectException(ClientException::class);

        $model->generateTextResult($prompt);
    }

    /**
     * Tests that generateTextResult() throws TokenLimitReachedException when stop_reason is "max_tokens".
     */
    public function testGenerateTextResultThrowsOnMaxTokensStopReason(): void
    {
        $prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hello')])];
        $response = new Response(
            200,
            [],
            json_encode([
                'id' => 'msg_123',
                'role' => 'assistant',
                'content' => [
                    ['type' => 'text', 'text' => 'Truncated...'],
                ],
                'stop_reason' => 'max_tokens',
                'usage' => ['input_tokens' => 10, 'output_tokens' => 100],
            ])
        );

        $this->mockRequestAuthentication
            ->expects($this->once())
            ->method('authenticateRequest')
            ->willReturnArgument(0);

        $this->mockHttpTransporter
            ->expects($this->once())
            ->method('send')
            ->willReturn($response);

        $config = new ModelConfig();
        $config->setMaxTokens(100);
        $model = $this->createModel($config);

        $this->expectException(TokenLimitReachedException::class);
        $this->expectExceptionMessage('Generation stopped due to token limit (100) with stop reason "max_tokens".');

        $model->generateTextResult($prompt);
    }

    /**
     * Tests that generateTextResult() uses default max tokens when none configured.
     */
    public function testGenerateTextResultThrowsOnMaxTokensWithDefaultLimit(): void
    {
        $prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hello')])];
        $response = new Response(
            200,
            [],
            json_encode([
                'id' => 'msg_123',
                'role' => 'assistant',
                'content' => [
                    ['type' => 'text', 'text' => 'Truncated...'],
                ],
                'stop_reason' => 'max_tokens',
                'usage' => ['input_tokens' => 10, 'output_tokens' => 4096],
            ])
        );

        $this->mockRequestAuthentication
            ->expects($this->once())
            ->method('authenticateRequest')
            ->willReturnArgument(0);

        $this->mockHttpTransporter
            ->expects($this->once())
            ->method('send')
            ->willReturn($response);

        $model = $this->createModel();

        $this->expectException(TokenLimitReachedException::class);
        $this->expectExceptionMessage('Generation stopped due to token limit (4096) with stop reason "max_tokens".');

        $model->generateTextResult($prompt);
    }

    /**
     * Tests that stop_reason "end_turn" maps to FinishReasonEnum::stop().
     */
    public function testEndTurnStopReasonMapsToStop(): void
    {
        $prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hello')])];
        $response = new Response(
            200,
            [],
            json_encode([
                'id' => 'msg_123',
                'role' => 'assistant',
                'content' => [['type' => 'text', 'text' => 'Done']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 5, 'output_tokens' => 2],
            ])
        );

        $this->mockRequestAuthentication
            ->expects($this->once())
            ->method('authenticateRequest')
            ->willReturnArgument(0);

        $this->mockHttpTransporter
            ->expects($this->once())
            ->method('send')
            ->willReturn($response);

        $model = $this->createModel();
        $result = $model->generateTextResult($prompt);

        $this->assertEquals(FinishReasonEnum::stop(), $result->getCandidates()[0]->getFinishReason());
    }

    /**
     * Tests that stop_reason "tool_use" maps to FinishReasonEnum::toolCalls().
     */
    public function testToolUseStopReasonMapsToToolCalls(): void
    {
        $prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Get the weather')])];
        $response = new Response(
            200,
            [],
            json_encode([
                'id' => 'msg_123',
                'role' => 'assistant',
                'content' => [
                    [
                        'type' => 'tool_use',
                        'id' => 'toolu_123',
                        'name' => 'get_weather',
                        'input' => ['location' => 'Paris'],
                    ],
                ],
                'stop_reason' => 'tool_use',
                'usage' => ['input_tokens' => 10, 'output_tokens' => 20],
            ])
        );

        $this->mockRequestAuthentication
            ->expects($this->once())
            ->method('authenticateRequest')
            ->willReturnArgument(0);

        $this->mockHttpTransporter
            ->expects($this->once())
            ->method('send')
            ->willReturn($response);

        $model = $this->createModel();
        $result = $model->generateTextResult($prompt);

        $this->assertEquals(FinishReasonEnum::toolCalls(), $result->getCandidates()[0]->getFinishReason());
    }

    /**
     * Tests that stop_reason "refusal" maps to FinishReasonEnum::contentFilter().
     */
    public function testRefusalStopReasonMapsToContentFilter(): void
    {
        $prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hello')])];
        $response = new Response(
            200,
            [],
            json_encode([
                'id' => 'msg_123',
                'role' => 'assistant',
                'content' => [['type' => 'text', 'text' => 'I cannot help with that.']],
                'stop_reason' => 'refusal',
                'usage' => ['input_tokens' => 5, 'output_tokens' => 10],
            ])
        );

        $this->mockRequestAuthentication
            ->expects($this->once())
            ->method('authenticateRequest')
            ->willReturnArgument(0);

        $this->mockHttpTransporter
            ->expects($this->once())
            ->method('send')
            ->willReturn($response);

        $model = $this->createModel();
        $result = $model->generateTextResult($prompt);

        $this->assertEquals(FinishReasonEnum::contentFilter(), $result->getCandidates()[0]->getFinishReason());
    }

    /**
     * Tests that token usage includes cache tokens.
     */
    public function testTokenUsageIncludesCacheTokens(): void
    {
        $prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hello')])];
        $response = new Response(
            200,
            [],
            json_encode([
                'id' => 'msg_123',
                'role' => 'assistant',
                'content' => [['type' => 'text', 'text' => 'Hi']],
                'stop_reason' => 'end_turn',
                'usage' => [
                    'input_tokens' => 10,
                    'output_tokens' => 5,
                    'cache_creation_input_tokens' => 100,
                    'cache_read_input_tokens' => 50,
                ],
            ])
        );

        $this->mockRequestAuthentication
            ->expects($this->once())
            ->method('authenticateRequest')
            ->willReturnArgument(0);

        $this->mockHttpTransporter
            ->expects($this->once())
            ->method('send')
            ->willReturn($response);

        $model = $this->createModel();
        $result = $model->generateTextResult($prompt);

        // input_tokens (10) + cache_creation (100) + cache_read (50) = 160
        $this->assertSame(160, $result->getTokenUsage()->getPromptTokens());
        $this->assertSame(5, $result->getTokenUsage()->getCompletionTokens());
        $this->assertSame(165, $result->getTokenUsage()->getTotalTokens());
    }

    /**
     * Tests prepareGenerateTextParams() includes default max_tokens.
     */
    public function testPrepareGenerateTextParamsIncludesDefaultMaxTokens(): void
    {
        $prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hello')])];

        $model = $this->createModel();
        $params = $model->exposePrepareGenerateTextParams($prompt);

        $this->assertArrayHasKey('max_tokens', $params);
        $this->assertSame(4096, $params['max_tokens']);
    }

    /**
     * Tests prepareGenerateTextParams() with custom max_tokens.
     */
    public function testPrepareGenerateTextParamsWithCustomMaxTokens(): void
    {
        $prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hello')])];
        $config = new ModelConfig();
        $config->setMaxTokens(1000);

        $model = $this->createModel($config);
        $params = $model->exposePrepareGenerateTextParams($prompt);

        $this->assertSame(1000, $params['max_tokens']);
    }

    /**
     * Tests prepareGenerateTextParams() with system instruction.
     */
    public function testPrepareGenerateTextParamsWithSystemInstruction(): void
    {
        $prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hello')])];
        $config = new ModelConfig();
        $config->setSystemInstruction('You are a helpful assistant.');

        $model = $this->createModel($config);
        $params = $model->exposePrepareGenerateTextParams($prompt);

        $this->assertArrayHasKey('system', $params);
        $this->assertSame('You are a helpful assistant.', $params['system']);
    }

    /**
     * Tests prepareGenerateTextParams() with JSON schema output.
     */
    public function testPrepareGenerateTextParamsWithJsonSchemaOutput(): void
    {
        $prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hello')])];
        $schema = ['type' => 'object', 'properties' => ['answer' => ['type' => 'string']]];
        $config = new ModelConfig();
        $config->setOutputMimeType('application/json');
        $config->setOutputSchema($schema);

        $model = $this->createModel($config);
        $params = $model->exposePrepareGenerateTextParams($prompt);

        $this->assertArrayHasKey('output_config', $params);
        $this->assertSame('json_schema', $params['output_config']['format']['type']);
        $this->assertSame($schema, $params['output_config']['format']['schema']);
    }

    /**
     * Tests prepareGenerateTextParams() with temperature and topP.
     */
    public function testPrepareGenerateTextParamsWithTemperatureAndTopP(): void
    {
        $prompt = [new Message(MessageRoleEnum::user(), [new MessagePart('Hello')])];
        $config = new ModelConfig();
        $config->setTemperature(0.7);
        $config->setTopP(0.9);

        $model = $this->createModel($config);
        $params = $model->exposePrepareGenerateTextParams($prompt);

        $this->assertSame(0.7, $params['temperature']);
        $this->assertSame(0.9, $params['top_p']);
    }
}
