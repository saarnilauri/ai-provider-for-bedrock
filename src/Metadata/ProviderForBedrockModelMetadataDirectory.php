<?php

declare(strict_types=1);

namespace AiProviderForBedrock\Metadata;

use AiProviderForBedrock\Provider\ProviderForBedrock;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory;

/**
 * Model metadata directory for Amazon Bedrock Claude models.
 *
 * Uses a hardcoded model list since Bedrock has no /models API endpoint.
 * When AWS releases new Claude models, update the BEDROCK_CLAUDE_MODELS constant.
 *
 * Models last updated: 2025-05-14
 *
 * @since 1.0.0
 * @see https://docs.aws.amazon.com/bedrock/latest/userguide/models-supported.html
 */
class ProviderForBedrockModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory
{
    /**
     * Hardcoded Bedrock Claude models.
     * Model ID => display name.
     */
    private const BEDROCK_CLAUDE_MODELS = [
        'anthropic.claude-opus-4-20250514' => 'Claude Opus 4.6',
        'anthropic.claude-sonnet-4-20250514' => 'Claude Sonnet 4.6',
        'anthropic.claude-haiku-4-20250514' => 'Claude Haiku 4.5',
    ];

    /**
     * Model sort order. Lower = higher priority.
     */
    private const MODEL_SORT_ORDER = [
        'opus' => 1,
        'sonnet' => 2,
        'haiku' => 3,
    ];

    /**
     * Returns hardcoded Claude models. No HTTP request is made.
     *
     * @since 1.0.0
     *
     * @return array<string, ModelMetadata> Keyed by model ID.
     */
    protected function sendListModelsRequest(): array
    {
        $capabilities = [
            CapabilityEnum::textGeneration(),
            CapabilityEnum::chatHistory(),
        ];

        $options = [
            new SupportedOption(OptionEnum::systemInstruction()),
            new SupportedOption(OptionEnum::maxTokens()),
            new SupportedOption(OptionEnum::temperature()),
            new SupportedOption(OptionEnum::topP()),
            new SupportedOption(OptionEnum::topK()),
            new SupportedOption(OptionEnum::stopSequences()),
            new SupportedOption(OptionEnum::outputMimeType(), ['text/plain', 'application/json']),
            new SupportedOption(OptionEnum::outputSchema()),
            new SupportedOption(OptionEnum::functionDeclarations()),
            new SupportedOption(OptionEnum::customOptions()),
            new SupportedOption(
                OptionEnum::inputModalities(),
                [
                    [ModalityEnum::text()],
                    [ModalityEnum::text(), ModalityEnum::image()],
                    [ModalityEnum::text(), ModalityEnum::document()],
                    [ModalityEnum::text(), ModalityEnum::image(), ModalityEnum::document()],
                ]
            ),
            new SupportedOption(OptionEnum::outputModalities(), [[ModalityEnum::text()]]),
        ];

        $models = [];
        foreach (self::BEDROCK_CLAUDE_MODELS as $modelId => $displayName) {
            $models[$modelId] = new ModelMetadata(
                $modelId,
                $displayName,
                $capabilities,
                $options
            );
        }

        // Sort: Opus > Sonnet > Haiku
        uasort($models, [$this, 'modelSortCallback']);

        return $models;
    }

    /**
     * Sorting callback for model metadata.
     *
     * @since 1.0.0
     */
    protected function modelSortCallback(ModelMetadata $a, ModelMetadata $b): int
    {
        return $this->getModelSortWeight($a->getId()) - $this->getModelSortWeight($b->getId());
    }

    /**
     * Returns the sort weight for a model ID based on its family.
     *
     * @since 1.0.0
     */
    private function getModelSortWeight(string $modelId): int
    {
        foreach (self::MODEL_SORT_ORDER as $family => $weight) {
            if (str_contains($modelId, $family)) {
                return $weight;
            }
        }
        return 99;
    }

    /**
     * {@inheritDoc}
     *
     * No-op: required by parent but unused since sendListModelsRequest is overridden.
     *
     * @since 1.0.0
     */
    protected function createRequest(
        HttpMethodEnum $method,
        string $path,
        array $headers = [],
        $data = null
    ): Request {
        return new Request($method, ProviderForBedrock::url($path), $headers, $data);
    }

    /**
     * {@inheritDoc}
     *
     * No-op: required by parent but unused since sendListModelsRequest is overridden.
     *
     * @since 1.0.0
     */
    protected function parseResponseToModelMetadataList(Response $response): array
    {
        return [];
    }
}
