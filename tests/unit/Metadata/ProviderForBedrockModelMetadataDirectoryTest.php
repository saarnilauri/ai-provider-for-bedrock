<?php

declare(strict_types=1);

namespace AiProviderForBedrock\Tests\Unit\Metadata;

use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

/**
 * @covers \AiProviderForBedrock\Metadata\ProviderForBedrockModelMetadataDirectory
 */
class ProviderForBedrockModelMetadataDirectoryTest extends TestCase
{
    /**
     * Builds an inference profiles API response from summaries.
     *
     * @param list<array<string, string>> $summaries
     */
    private function createProfilesResponse(array $summaries): Response
    {
        return new Response(200, [], json_encode(['inferenceProfileSummaries' => $summaries]));
    }

    /**
     * Tests that only active Anthropic profiles are included.
     */
    public function testParseInferenceProfilesFiltersToActiveAnthropicModels(): void
    {
        $directory = new MockProviderForBedrockModelMetadataDirectory();
        $profiles = $directory->exposeParseInferenceProfilesResponse($this->createProfilesResponse([
            [
                'inferenceProfileId' => 'us.anthropic.claude-sonnet-4-6',
                'inferenceProfileName' => 'US Anthropic Claude Sonnet 4.6',
                'status' => 'ACTIVE',
            ],
            [
                'inferenceProfileId' => 'us.anthropic.claude-opus-4-6-v1',
                'inferenceProfileName' => 'US Anthropic Claude Opus 4.6',
                'status' => 'INACTIVE',
            ],
            [
                'inferenceProfileId' => 'us.meta.llama4-maverick-v1:0',
                'inferenceProfileName' => 'US Meta Llama 4 Maverick',
                'status' => 'ACTIVE',
            ],
        ]));

        $this->assertSame(
            ['us.anthropic.claude-sonnet-4-6' => 'Claude Sonnet 4.6'],
            $profiles
        );
    }

    /**
     * Tests that geo-specific profiles are preferred over global duplicates,
     * while global-only models are still included.
     */
    public function testParseInferenceProfilesPrefersGeoProfileOverGlobal(): void
    {
        // The stubbed region is us-east-1, so "us." profiles are preferred.
        $directory = new MockProviderForBedrockModelMetadataDirectory();
        $profiles = $directory->exposeParseInferenceProfilesResponse($this->createProfilesResponse([
            [
                'inferenceProfileId' => 'global.anthropic.claude-sonnet-4-6',
                'inferenceProfileName' => 'Global Anthropic Claude Sonnet 4.6',
                'status' => 'ACTIVE',
            ],
            [
                'inferenceProfileId' => 'us.anthropic.claude-sonnet-4-6',
                'inferenceProfileName' => 'US Anthropic Claude Sonnet 4.6',
                'status' => 'ACTIVE',
            ],
            [
                'inferenceProfileId' => 'global.anthropic.claude-fable-5',
                'inferenceProfileName' => 'Global Anthropic Claude Fable 5',
                'status' => 'ACTIVE',
            ],
        ]));

        $this->assertSame(
            [
                'us.anthropic.claude-sonnet-4-6' => 'Claude Sonnet 4.6',
                'global.anthropic.claude-fable-5' => 'Claude Fable 5',
            ],
            $profiles
        );
    }

    /**
     * Tests that a malformed response yields no profiles.
     */
    public function testParseInferenceProfilesHandlesMalformedResponse(): void
    {
        $directory = new MockProviderForBedrockModelMetadataDirectory();

        $this->assertSame(
            [],
            $directory->exposeParseInferenceProfilesResponse(new Response(200, [], json_encode(['foo' => 'bar'])))
        );
        $this->assertSame(
            [],
            $directory->exposeParseInferenceProfilesResponse(new Response(200, [], 'not json'))
        );
    }

    /**
     * Tests that hardcoded model list returns all expected Claude models.
     */
    public function testSendListModelsRequestReturnsHardcodedModels(): void
    {
        $directory = new MockProviderForBedrockModelMetadataDirectory();
        $models = $directory->exposeSendListModelsRequest();

        $this->assertCount(3, $models);

        // The stubbed region is us-east-1, so IDs get the "us." geo prefix.
        $modelIds = array_keys($models);
        $this->assertContains('us.anthropic.claude-opus-4-6-v1', $modelIds);
        $this->assertContains('us.anthropic.claude-sonnet-4-6', $modelIds);
        $this->assertContains('us.anthropic.claude-haiku-4-5-20251001-v1:0', $modelIds);
    }

    /**
     * Tests that models are sorted by priority: Opus > Sonnet > Haiku.
     */
    public function testModelsAreSortedByPriority(): void
    {
        $directory = new MockProviderForBedrockModelMetadataDirectory();
        $models = $directory->exposeSendListModelsRequest();

        $modelIds = array_keys($models);
        $this->assertSame('us.anthropic.claude-opus-4-6-v1', $modelIds[0]);
        $this->assertSame('us.anthropic.claude-sonnet-4-6', $modelIds[1]);
        $this->assertSame('us.anthropic.claude-haiku-4-5-20251001-v1:0', $modelIds[2]);
    }

    /**
     * Tests that all models have correct capabilities.
     */
    public function testAllModelsHaveTextGenerationCapability(): void
    {
        $directory = new MockProviderForBedrockModelMetadataDirectory();
        $models = $directory->exposeSendListModelsRequest();

        foreach ($models as $modelId => $model) {
            $this->assertInstanceOf(ModelMetadata::class, $model);
            $this->assertContains(
                CapabilityEnum::textGeneration(),
                $model->getSupportedCapabilities(),
                "Model {$modelId} should support text generation"
            );
            $this->assertContains(
                CapabilityEnum::chatHistory(),
                $model->getSupportedCapabilities(),
                "Model {$modelId} should support chat history"
            );
        }
    }

    /**
     * Tests that all models have the expected supported options.
     */
    public function testAllModelsHaveExpectedOptions(): void
    {
        $directory = new MockProviderForBedrockModelMetadataDirectory();
        $models = $directory->exposeSendListModelsRequest();

        $expectedOptionNames = [
            OptionEnum::systemInstruction()->value,
            OptionEnum::maxTokens()->value,
            OptionEnum::temperature()->value,
            OptionEnum::topP()->value,
            OptionEnum::topK()->value,
            OptionEnum::stopSequences()->value,
            OptionEnum::outputMimeType()->value,
            OptionEnum::outputSchema()->value,
            OptionEnum::functionDeclarations()->value,
            OptionEnum::customOptions()->value,
            OptionEnum::inputModalities()->value,
            OptionEnum::outputModalities()->value,
        ];

        foreach ($models as $modelId => $model) {
            $optionNames = array_map(
                static fn (SupportedOption $option): string => $option->getName()->value,
                $model->getSupportedOptions()
            );

            foreach ($expectedOptionNames as $expected) {
                $this->assertContains(
                    $expected,
                    $optionNames,
                    "Model {$modelId} should support option {$expected}"
                );
            }
        }
    }

    /**
     * Tests that models have correct display names.
     */
    public function testModelsHaveCorrectDisplayNames(): void
    {
        $directory = new MockProviderForBedrockModelMetadataDirectory();
        $models = $directory->exposeSendListModelsRequest();

        $this->assertSame('Claude Opus 4.6', $models['us.anthropic.claude-opus-4-6-v1']->getName());
        $this->assertSame('Claude Sonnet 4.6', $models['us.anthropic.claude-sonnet-4-6']->getName());
        $this->assertSame('Claude Haiku 4.5', $models['us.anthropic.claude-haiku-4-5-20251001-v1:0']->getName());
    }

    /**
     * Tests that input modalities include text, image, and document combinations.
     */
    public function testModelsHaveCorrectInputModalities(): void
    {
        $directory = new MockProviderForBedrockModelMetadataDirectory();
        $models = $directory->exposeSendListModelsRequest();

        $model = reset($models);
        $inputModalitiesOption = $this->findOption($model, OptionEnum::inputModalities());
        $this->assertNotNull($inputModalitiesOption);

        $this->assertTrue(
            $this->supportedModalitiesInclude(
                $inputModalitiesOption->getSupportedValues() ?? [],
                ['text']
            ),
            'Should support text-only input'
        );

        $this->assertTrue(
            $this->supportedModalitiesInclude(
                $inputModalitiesOption->getSupportedValues() ?? [],
                ['text', 'image']
            ),
            'Should support text+image input'
        );

        $this->assertTrue(
            $this->supportedModalitiesInclude(
                $inputModalitiesOption->getSupportedValues() ?? [],
                ['document', 'text']
            ),
            'Should support text+document input'
        );
    }

    /**
     * Finds a supported option by name.
     */
    private function findOption(ModelMetadata $model, OptionEnum $option): ?SupportedOption
    {
        foreach ($model->getSupportedOptions() as $supportedOption) {
            if ($supportedOption->getName()->is($option)) {
                return $supportedOption;
            }
        }

        return null;
    }

    /**
     * Checks if the supported modality values include the expected set.
     *
     * @param list<mixed> $supportedValues
     * @param list<string> $expected
     */
    private function supportedModalitiesInclude(array $supportedValues, array $expected): bool
    {
        foreach ($supportedValues as $value) {
            if (!is_array($value)) {
                continue;
            }

            $modalities = array_map(
                static function ($modality): ?string {
                    return $modality instanceof ModalityEnum ? $modality->value : null;
                },
                $value
            );

            $modalities = array_values(array_filter($modalities));
            sort($modalities);

            $expectedSorted = $expected;
            sort($expectedSorted);

            if ($modalities === $expectedSorted) {
                return true;
            }
        }

        return false;
    }
}
