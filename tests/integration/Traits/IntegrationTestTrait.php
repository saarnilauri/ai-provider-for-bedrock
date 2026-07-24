<?php

declare(strict_types=1);

namespace AiProviderForBedrock\Tests\Integration\Traits;

/**
 * Trait providing shared functionality for integration tests.
 *
 * This trait provides utility methods for integration tests that make
 * real API calls to AI providers.
 */
trait IntegrationTestTrait
{
    /**
     * Skips the test if the specified environment variable is not set.
     *
     * @param string $envVar The name of the environment variable to check.
     */
    protected function requireApiKey(string $envVar): void
    {
        // Check both $_ENV (populated by symfony/dotenv) and getenv() (shell environment)
        $value = $_ENV[$envVar] ?? getenv($envVar);
        if ($value === false || $value === '' || $value === null) {
            $this->markTestSkipped("Skipping: {$envVar} environment variable is not set.");
        }
    }

    /**
     * Reads an environment variable from $_ENV or getenv().
     *
     * @param string $envVar The name of the environment variable.
     * @return string The value, or an empty string if not set.
     */
    private function getEnvVar(string $envVar): string
    {
        $value = $_ENV[$envVar] ?? getenv($envVar);
        return is_string($value) ? $value : '';
    }

    /**
     * Sets up Bedrock credentials for the plugin helper functions.
     *
     * Requires either BEDROCK_API_KEY (Bearer token auth) or the
     * BEDROCK_ACCESS_KEY_ID/BEDROCK_SECRET_ACCESS_KEY pair (SigV4 auth)
     * to be present in the environment; skips the test otherwise.
     * Defines the corresponding PHP constants and loads plugin.php.
     */
    protected function setUpBedrockCredentials(): void
    {
        $apiKey = $this->getEnvVar('BEDROCK_API_KEY');
        $accessKeyId = $this->getEnvVar('BEDROCK_ACCESS_KEY_ID');
        $secretAccessKey = $this->getEnvVar('BEDROCK_SECRET_ACCESS_KEY');

        $hasApiKey = $apiKey !== '';
        $hasKeyPair = $accessKeyId !== '' && $secretAccessKey !== '';

        if (!$hasApiKey && !$hasKeyPair) {
            $this->markTestSkipped(
                'Skipping: set BEDROCK_API_KEY, or both BEDROCK_ACCESS_KEY_ID and BEDROCK_SECRET_ACCESS_KEY.'
            );
        }

        if ($hasApiKey && !defined('BEDROCK_API_KEY')) {
            define('BEDROCK_API_KEY', $apiKey);
        }
        if ($hasKeyPair) {
            if (!defined('BEDROCK_ACCESS_KEY_ID')) {
                define('BEDROCK_ACCESS_KEY_ID', $accessKeyId);
            }
            if (!defined('BEDROCK_SECRET_ACCESS_KEY')) {
                define('BEDROCK_SECRET_ACCESS_KEY', $secretAccessKey);
            }
        }
        if (!defined('BEDROCK_REGION')) {
            define('BEDROCK_REGION', $this->getEnvVar('BEDROCK_REGION') ?: 'eu-north-1');
        }

        // Load the plugin helper functions.
        require_once dirname(__DIR__, 3) . '/plugin.php';
    }
}
