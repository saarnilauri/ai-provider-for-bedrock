<?php

declare(strict_types=1);

namespace AiProviderForBedrock\Metadata;

use AiProviderForBedrock\Authentication\BedrockRequestAuthentication;
use AiProviderForBedrock\Provider\ProviderForBedrock;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
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
 * Fetches the available Claude models from the Bedrock control-plane
 * inference profiles API, falling back to a hardcoded list when the API
 * is unreachable (e.g. missing permissions, network failure, or outside
 * WordPress). Results are cached in a transient for one hour.
 *
 * Model IDs are cross-region inference profile IDs (with a geo prefix like
 * "eu." derived from the configured region), because these Claude models do
 * not support on-demand invocation of the bare foundation-model ID.
 *
 * Fallback models last updated: 2026-07-24
 *
 * @since 1.0.0
 * @see https://docs.aws.amazon.com/bedrock/latest/userguide/inference-profiles-support.html
 */
class ProviderForBedrockModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory
{
    /**
     * Transient name prefix for caching the fetched model list.
     */
    private const CACHE_TRANSIENT_PREFIX = 'ai_provider_for_bedrock_models_';

    /**
     * Cache lifetime for the fetched model list, in seconds.
     */
    private const CACHE_TTL = 3600;

    /**
     * Hardcoded fallback Bedrock Claude models.
     * Foundation model ID (without geo prefix) => display name.
     */
    private const BEDROCK_CLAUDE_MODELS = [
        'anthropic.claude-opus-4-6-v1' => 'Claude Opus 4.6',
        'anthropic.claude-sonnet-4-6' => 'Claude Sonnet 4.6',
        'anthropic.claude-haiku-4-5-20251001-v1:0' => 'Claude Haiku 4.5',
    ];

    /**
     * Model sort order. Lower = higher priority.
     */
    /*
     * Fable sorts after the standard families on purpose: it requires
     * account-level data retention configuration on Bedrock, so it must not
     * be the default model that automatic selection picks first.
     */
    private const MODEL_SORT_ORDER = [
        'opus' => 1,
        'sonnet' => 2,
        'haiku' => 3,
        'fable' => 4,
    ];

    /**
     * Returns the available Claude models.
     *
     * Tries the Bedrock inference profiles API first and falls back to the
     * hardcoded list on any failure.
     *
     * @since 1.0.0
     *
     * @return array<string, ModelMetadata> Keyed by inference profile ID.
     */
    protected function sendListModelsRequest(): array
    {
        try {
            $profiles = $this->fetchInferenceProfiles();
        } catch (\Throwable $e) {
            $profiles = [];
        }

        if ($profiles === []) {
            $profiles = $this->getFallbackProfiles();
        }

        $models = [];
        foreach ($profiles as $profileId => $displayName) {
            $models[$profileId] = $this->createClaudeModelMetadata($profileId, $displayName);
        }

        // Sort: Opus > Sonnet > Haiku > Fable
        uasort($models, [$this, 'modelSortCallback']);

        return $models;
    }

    /**
     * Fetches available Claude inference profiles from the Bedrock control-plane API.
     *
     * @since 1.0.0
     *
     * @return array<string, string> Inference profile ID => display name. Empty on failure.
     */
    protected function fetchInferenceProfiles(): array
    {
        $region = \AiProviderForBedrock\get_bedrock_region();
        $cacheKey = self::CACHE_TRANSIENT_PREFIX . md5($region);

        if (function_exists('get_transient')) {
            $cached = get_transient($cacheKey);
            if (is_array($cached) && $cached !== []) {
                /** @var array<string, string> $cached */
                return $cached;
            }
        }

        $request = new Request(
            HttpMethodEnum::GET(),
            'https://bedrock.' . $region . '.amazonaws.com/inference-profiles?maxResults=100',
            ['Accept' => 'application/json']
        );
        $request = $this->resolveRequestAuthentication()->authenticateRequest($request);

        $response = $this->getHttpTransporter()->send($request);
        if (!$response->isSuccessful()) {
            return [];
        }

        $profiles = $this->parseInferenceProfilesResponse($response);

        if ($profiles !== [] && function_exists('set_transient')) {
            set_transient($cacheKey, $profiles, self::CACHE_TTL);
        }

        return $profiles;
    }

    /**
     * Parses an inference profiles API response into a Claude model list.
     *
     * Filters to active Anthropic profiles and de-duplicates models available
     * under both a geo-specific (e.g. "eu.") and a "global." profile,
     * preferring the geo-specific one for the configured region.
     *
     * @since 1.0.0
     *
     * @param Response $response The inference profiles API response.
     * @return array<string, string> Inference profile ID => display name.
     */
    protected function parseInferenceProfilesResponse(Response $response): array
    {
        $data = $response->getData();
        if (
            !is_array($data)
            || !isset($data['inferenceProfileSummaries'])
            || !is_array($data['inferenceProfileSummaries'])
        ) {
            return [];
        }

        $geoPrefix = self::getGeoPrefix();

        /** @var array<string, array{id: string, name: string, preferred: bool}> $byBaseId */
        $byBaseId = [];
        foreach ($data['inferenceProfileSummaries'] as $summary) {
            if (!is_array($summary)) {
                continue;
            }
            $profileId = $summary['inferenceProfileId'] ?? '';
            $status = $summary['status'] ?? '';
            if (!is_string($profileId) || $status !== 'ACTIVE' || !str_contains($profileId, 'anthropic.')) {
                continue;
            }

            $displayName = $summary['inferenceProfileName'] ?? '';
            $displayName = is_string($displayName) && $displayName !== ''
                ? $this->normalizeDisplayName($displayName)
                : $profileId;

            $baseId = (string) preg_replace('/^[a-z-]+\./', '', $profileId);
            $preferred = str_starts_with($profileId, $geoPrefix);

            if (isset($byBaseId[$baseId]) && $byBaseId[$baseId]['preferred'] && !$preferred) {
                continue;
            }
            $byBaseId[$baseId] = [
                'id' => $profileId,
                'name' => $displayName,
                'preferred' => $preferred,
            ];
        }

        $profiles = [];
        foreach ($byBaseId as $entry) {
            $profiles[$entry['id']] = $entry['name'];
        }

        return $profiles;
    }

    /**
     * Normalizes an inference profile display name.
     *
     * Strips the leading geo qualifier and vendor name, e.g.
     * "EU Anthropic Claude Opus 4.6" becomes "Claude Opus 4.6".
     *
     * @since 1.0.0
     *
     * @param string $name The raw inference profile name.
     * @return string The normalized display name.
     */
    private function normalizeDisplayName(string $name): string
    {
        $name = (string) preg_replace('/^(EU|US|APAC|GLOBAL)\s+/i', '', $name);
        return (string) preg_replace('/^Anthropic\s+/i', '', $name);
    }

    /**
     * Returns the request authentication for the control-plane API call.
     *
     * Prefers authentication injected by the registry (e.g. an API key stored
     * via the WordPress core connectors settings page) and falls back to
     * plugin settings.
     *
     * @since 1.0.0
     *
     * @return RequestAuthenticationInterface
     */
    protected function resolveRequestAuthentication(): RequestAuthenticationInterface
    {
        try {
            $requestAuthentication = $this->getRequestAuthentication();
        } catch (RuntimeException $e) {
            $requestAuthentication = null;
        }

        if (
            $requestAuthentication instanceof ApiKeyRequestAuthentication
            && $requestAuthentication->getApiKey() === ''
        ) {
            $requestAuthentication = null;
        }

        if ($requestAuthentication !== null) {
            return $requestAuthentication;
        }

        return BedrockRequestAuthentication::createFromSettings();
    }

    /**
     * Returns the hardcoded fallback models with the region geo prefix applied.
     *
     * @since 1.0.0
     *
     * @return array<string, string> Inference profile ID => display name.
     */
    protected function getFallbackProfiles(): array
    {
        $geoPrefix = self::getGeoPrefix();

        $profiles = [];
        foreach (self::BEDROCK_CLAUDE_MODELS as $modelId => $displayName) {
            $profiles[$geoPrefix . $modelId] = $displayName;
        }

        return $profiles;
    }

    /**
     * Creates the model metadata for a Claude model.
     *
     * The inference profiles API returns no capability information, so all
     * Claude models share the same capability and option set.
     *
     * @since 1.0.0
     *
     * @param string $modelId The inference profile ID.
     * @param string $displayName The display name.
     * @return ModelMetadata The model metadata.
     */
    protected function createClaudeModelMetadata(string $modelId, string $displayName): ModelMetadata
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

        return new ModelMetadata($modelId, $displayName, $capabilities, $options);
    }

    /**
     * Returns the cross-region inference profile geo prefix for the configured region.
     *
     * Bedrock inference profiles are prefixed with the geographic area of the
     * region they route within, e.g. "eu." for eu-north-1 or "us." for us-east-1.
     *
     * @since 1.0.0
     *
     * @return string The geo prefix, including the trailing dot.
     */
    private static function getGeoPrefix(): string
    {
        $region = \AiProviderForBedrock\get_bedrock_region();
        $geo = explode('-', $region)[0];
        return $geo . '.';
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
     * Returns the sort weight for a model ID.
     *
     * Sorts by model family, and within a family prefers the curated models
     * from the fallback list. The inference profiles API exposes no
     * entitlement information, so a discovered model may not be enabled for
     * the account — automatic model selection picks the first model, which
     * must be a known-good one.
     *
     * @since 1.0.0
     */
    private function getModelSortWeight(string $modelId): int
    {
        $weight = 990;
        foreach (self::MODEL_SORT_ORDER as $family => $familyWeight) {
            if (str_contains($modelId, $family)) {
                $weight = $familyWeight * 10;
                break;
            }
        }

        $baseId = (string) preg_replace('/^[a-z-]+\./', '', $modelId);
        if (isset(self::BEDROCK_CLAUDE_MODELS[$baseId])) {
            $weight -= 5;
        }

        return $weight;
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
