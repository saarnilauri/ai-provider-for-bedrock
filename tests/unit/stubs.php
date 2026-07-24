<?php
/**
 * Stub functions for unit tests.
 *
 * These stubs replace the plugin.php helper functions that require WordPress.
 */

declare(strict_types=1);

namespace AiProviderForBedrock;

function get_bedrock_region(): string
{
    return 'us-east-1';
}

function get_bedrock_api_key(): string
{
    return '';
}

function get_bedrock_access_key_id(): string
{
    return 'test-access-key';
}

function get_bedrock_secret_access_key(): string
{
    return 'test-secret-key';
}
