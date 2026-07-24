<?php

/**
 * Plugin Name: AI Provider for Claude on Bedrock
 * Plugin URI: https://github.com/saarnilauri/ai-provider-for-bedrock
 * Description: Amazon Bedrock Claude provider for WordPress AI Client
 * Requires at least: 6.9
 * Requires PHP: 8.1
 * Version: 1.0.0
 * Author: Lauri Saarni
 * Author URI: https://profiles.wordpress.org/laurisaarni/
 * License: GPL-2.0-or-later
 * License URI: https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain: ai-provider-for-bedrock
 *
 * @package AiProviderForBedrock
 */

declare(strict_types=1);

namespace AiProviderForBedrock;

use AiProviderForBedrock\Authentication\BedrockRequestAuthentication;
use AiProviderForBedrock\Provider\ProviderForBedrock;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

if (!defined('ABSPATH')) {
    return;
}

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Loads all plugin class files.
 *
 * Load order: Metadata -> Authentication -> Models -> Provider
 *
 * @since 1.0.0
 *
 * @return void
 */
function load_classes(): void
{
    $plugin_dir = __DIR__ . '/src';

    require_once $plugin_dir . '/Metadata/ProviderForBedrockModelMetadataDirectory.php';
    require_once $plugin_dir . '/Authentication/BedrockRequestAuthentication.php';
    require_once $plugin_dir . '/Models/ProviderForBedrockTextGenerationModel.php';
    require_once $plugin_dir . '/Provider/BedrockProviderAvailability.php';
    require_once $plugin_dir . '/Provider/ProviderForBedrock.php';
}

/**
 * Registers the WordPress AI Client provider for Amazon Bedrock.
 *
 * @since 1.0.0
 *
 * @return void
 */
function register_provider(): void
{
    if (!class_exists(AiClient::class)) {
        return;
    }

    load_classes();

    $registry = AiClient::defaultRegistry();

    if ($registry->hasProvider(ProviderForBedrock::class)) {
        return;
    }

    $registry->registerProvider(ProviderForBedrock::class);
}

add_action('init', __NAMESPACE__ . '\\register_provider', 5);

/**
 * Applies plugin-configured Bedrock authentication as a fallback after AI_Client::init().
 *
 * An API key managed via the WordPress core connectors settings page (applied by
 * the credentials manager at init priority 10) takes precedence and is left
 * untouched — Bedrock API keys work with the generic Bearer authentication. This
 * hook only fills the gap when no usable authentication is set on the registry,
 * using the plugin's own settings: a Bedrock API key, or an IAM access key pair
 * for AWS Signature V4 signing.
 *
 * @since 1.0.0
 *
 * @return void
 */
function restore_bedrock_authentication(): void
{
    if (!class_exists(AiClient::class)) {
        return;
    }

    $registry = AiClient::defaultRegistry();

    if (!$registry->hasProvider(ProviderForBedrock::class)) {
        return;
    }

    $currentAuth = $registry->getProviderRequestAuthentication(ProviderForBedrock::class);

    if ($currentAuth instanceof BedrockRequestAuthentication) {
        return;
    }

    if ($currentAuth instanceof ApiKeyRequestAuthentication && $currentAuth->getApiKey() !== '') {
        // A key stored via the core connectors page (or BEDROCK_API_KEY) is in use.
        return;
    }

    try {
        $desiredAuth = BedrockRequestAuthentication::createFromSettings();
    } catch (\RuntimeException $e) {
        // Credentials not configured yet; silently return.
        return;
    }

    $registry->setProviderRequestAuthentication(ProviderForBedrock::class, $desiredAuth);
}

add_action('init', __NAMESPACE__ . '\\restore_bedrock_authentication', 11);

/**
 * Gets the Amazon Bedrock API key.
 *
 * Bedrock API keys (long-term or short-term) are sent as a Bearer token
 * instead of signing requests with AWS Signature V4. When a non-empty API key
 * is configured, it takes precedence over the access key pair.
 *
 * @since 1.0.0
 *
 * @return string The Bedrock API key.
 */
function get_bedrock_api_key(): string
{
    if (defined('BEDROCK_API_KEY')) {
        return (string) constant('BEDROCK_API_KEY');
    }
    if (!function_exists('get_option')) {
        // Outside WordPress (e.g. tests), only the constant is supported.
        return '';
    }
    $option = get_option('bedrock_ai_api_key', '');
    return is_string($option) ? $option : '';
}

/**
 * Gets the AWS access key ID for Bedrock.
 *
 * @since 1.0.0
 *
 * @return string The AWS access key ID.
 */
function get_bedrock_access_key_id(): string
{
    if (defined('BEDROCK_ACCESS_KEY_ID')) {
        return (string) constant('BEDROCK_ACCESS_KEY_ID');
    }
    if (!function_exists('get_option')) {
        // Outside WordPress (e.g. tests), only the constant is supported.
        return '';
    }
    $option = get_option('bedrock_ai_access_key_id', '');
    return is_string($option) ? $option : '';
}

/**
 * Gets the AWS secret access key for Bedrock.
 *
 * @since 1.0.0
 *
 * @return string The AWS secret access key.
 */
function get_bedrock_secret_access_key(): string
{
    if (defined('BEDROCK_SECRET_ACCESS_KEY')) {
        return (string) constant('BEDROCK_SECRET_ACCESS_KEY');
    }
    if (!function_exists('get_option')) {
        // Outside WordPress (e.g. tests), only the constant is supported.
        return '';
    }
    $option = get_option('bedrock_ai_secret_access_key', '');
    return is_string($option) ? $option : '';
}

/**
 * Gets the AWS region for Bedrock.
 *
 * @since 1.0.0
 *
 * @return string The AWS region.
 */
function get_bedrock_region(): string
{
    if (defined('BEDROCK_REGION')) {
        return (string) constant('BEDROCK_REGION');
    }
    if (!function_exists('get_option')) {
        // Outside WordPress (e.g. tests), only the constant is supported.
        return 'eu-north-1';
    }
    $option = get_option('bedrock_ai_region', 'eu-north-1');
    return is_string($option) && $option !== '' ? $option : 'eu-north-1';
}

// ---------------------------------------------------------------------------
// Setter functions
// ---------------------------------------------------------------------------

/**
 * Sets the Bedrock API key option.
 *
 * @since 1.0.0
 *
 * @param string $value The Bedrock API key.
 * @return void
 */
function set_bedrock_api_key(string $value): void
{
    update_option('bedrock_ai_api_key', $value);
}

/**
 * Sets the AWS access key ID option.
 *
 * @since 1.0.0
 *
 * @param string $value The AWS access key ID.
 * @return void
 */
function set_bedrock_access_key_id(string $value): void
{
    update_option('bedrock_ai_access_key_id', $value);
}

/**
 * Sets the AWS secret access key option.
 *
 * @since 1.0.0
 *
 * @param string $value The AWS secret access key.
 * @return void
 */
function set_bedrock_secret_access_key(string $value): void
{
    update_option('bedrock_ai_secret_access_key', $value);
}

/**
 * Sets the AWS region option.
 *
 * @since 1.0.0
 *
 * @param string $value The AWS region.
 * @return void
 */
function set_bedrock_region(string $value): void
{
    update_option('bedrock_ai_region', $value);
}

// ---------------------------------------------------------------------------
// Sanitization callbacks
// ---------------------------------------------------------------------------

/**
 * Sanitizes the Bedrock API key.
 *
 * Trims whitespace. Kept otherwise as-is because Bedrock API keys are
 * base64-encoded and may contain special characters.
 *
 * @since 1.0.0
 *
 * @param string $value Raw input value.
 * @return string Sanitized value.
 */
function sanitize_bedrock_api_key(string $value): string
{
    return trim($value);
}

/**
 * Sanitizes the AWS access key ID.
 *
 * Allows alphanumeric characters and hyphens only. Trims whitespace.
 *
 * @since 1.0.0
 *
 * @param string $value Raw input value.
 * @return string Sanitized value.
 */
function sanitize_bedrock_access_key(string $value): string
{
    $value = trim($value);
    return preg_replace('/[^A-Za-z0-9\-]/', '', $value);
}

/**
 * Sanitizes the AWS secret access key.
 *
 * Kept as-is because AWS secret keys may contain special characters.
 *
 * @since 1.0.0
 *
 * @param string $value Raw input value.
 * @return string The value unchanged.
 */
function sanitize_bedrock_secret_access_key(string $value): string
{
    return $value;
}

/**
 * Sanitizes the AWS region.
 *
 * Validates against a list of allowed regions. Falls back to eu-north-1.
 *
 * @since 1.0.0
 *
 * @param string $value Raw input value.
 * @return string Validated region.
 */
function sanitize_bedrock_region(string $value): string
{
    $allowed = get_allowed_regions();
    return in_array($value, $allowed, true) ? $value : 'eu-north-1';
}

/**
 * Returns the list of allowed AWS regions.
 *
 * @since 1.0.0
 *
 * @return array<int, string> List of region identifiers.
 */
function get_allowed_regions(): array
{
    return [
        'eu-north-1',
        'us-east-1',
        'us-west-2',
        'eu-west-1',
        'ap-southeast-1',
    ];
}

// ---------------------------------------------------------------------------
// Admin settings page
// ---------------------------------------------------------------------------

/**
 * Registers the admin menu page for Bedrock AI Provider settings.
 *
 * @since 1.0.0
 *
 * @return void
 */
function add_settings_page(): void
{
    add_options_page(
        __('Bedrock AI Provider', 'ai-provider-for-bedrock'),
        __('Bedrock AI Provider', 'ai-provider-for-bedrock'),
        'manage_options',
        'bedrock-ai-settings',
        __NAMESPACE__ . '\\render_settings_page'
    );
}

add_action('admin_menu', __NAMESPACE__ . '\\add_settings_page');

/**
 * Registers settings, sections, and fields for the admin page.
 *
 * @since 1.0.0
 *
 * @return void
 */
function register_settings(): void
{
    // Register settings.
    register_setting('bedrock_ai_settings', 'bedrock_ai_api_key', [
        'type'              => 'string',
        'sanitize_callback' => __NAMESPACE__ . '\\sanitize_bedrock_api_key',
        'default'           => '',
    ]);
    register_setting('bedrock_ai_settings', 'bedrock_ai_access_key_id', [
        'type'              => 'string',
        'sanitize_callback' => __NAMESPACE__ . '\\sanitize_bedrock_access_key',
        'default'           => '',
    ]);
    register_setting('bedrock_ai_settings', 'bedrock_ai_secret_access_key', [
        'type'              => 'string',
        'sanitize_callback' => __NAMESPACE__ . '\\sanitize_bedrock_secret_access_key',
        'default'           => '',
    ]);
    register_setting('bedrock_ai_settings', 'bedrock_ai_region', [
        'type'              => 'string',
        'sanitize_callback' => __NAMESPACE__ . '\\sanitize_bedrock_region',
        'default'           => 'eu-north-1',
    ]);

    // Add section.
    add_settings_section(
        'bedrock_ai_main',
        __('Bedrock Configuration', 'ai-provider-for-bedrock'),
        '__return_false',
        'bedrock-ai-settings'
    );

    // Add fields.
    add_settings_field(
        'bedrock_ai_api_key',
        __('Bedrock API Key', 'ai-provider-for-bedrock'),
        __NAMESPACE__ . '\\render_api_key_field',
        'bedrock-ai-settings',
        'bedrock_ai_main'
    );
    add_settings_field(
        'bedrock_ai_access_key_id',
        __('AWS Access Key ID', 'ai-provider-for-bedrock'),
        __NAMESPACE__ . '\\render_access_key_field',
        'bedrock-ai-settings',
        'bedrock_ai_main'
    );
    add_settings_field(
        'bedrock_ai_secret_access_key',
        __('AWS Secret Access Key', 'ai-provider-for-bedrock'),
        __NAMESPACE__ . '\\render_secret_key_field',
        'bedrock-ai-settings',
        'bedrock_ai_main'
    );
    add_settings_field(
        'bedrock_ai_region',
        __('AWS Region', 'ai-provider-for-bedrock'),
        __NAMESPACE__ . '\\render_region_field',
        'bedrock-ai-settings',
        'bedrock_ai_main'
    );
}

add_action('admin_init', __NAMESPACE__ . '\\register_settings');

/**
 * Renders the settings page.
 *
 * @since 1.0.0
 *
 * @return void
 */
function render_settings_page(): void
{
    echo '<div class="wrap">';
    echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields('bedrock_ai_settings');
    do_settings_sections('bedrock-ai-settings');
    submit_button();
    echo '</form>';
    echo '</div>';
}

/**
 * Renders the Bedrock API Key field.
 *
 * @since 1.0.0
 *
 * @return void
 */
function render_api_key_field(): void
{
    $value    = get_option('bedrock_ai_api_key', '');
    $disabled = defined('BEDROCK_API_KEY');

    printf(
        '<input type="password" id="bedrock_ai_api_key" name="bedrock_ai_api_key"'
        . ' value="%s" class="regular-text" %s />',
        esc_attr(is_string($value) ? $value : ''),
        $disabled ? 'disabled="disabled"' : ''
    );

    echo '<p class="description">';
    if ($disabled) {
        echo esc_html__('Currently overridden by PHP constant BEDROCK_API_KEY.', 'ai-provider-for-bedrock');
    } else {
        echo esc_html__(
            'Optional. When set, the API key is used instead of the AWS access key pair below.',
            'ai-provider-for-bedrock'
        );
    }
    echo '</p>';
}

/**
 * Renders the AWS Access Key ID field.
 *
 * @since 1.0.0
 *
 * @return void
 */
function render_access_key_field(): void
{
    $value    = get_option('bedrock_ai_access_key_id', '');
    $disabled = defined('BEDROCK_ACCESS_KEY_ID');

    printf(
        '<input type="text" id="bedrock_ai_access_key_id" name="bedrock_ai_access_key_id"'
        . ' value="%s" class="regular-text" %s />',
        esc_attr(is_string($value) ? $value : ''),
        $disabled ? 'disabled="disabled"' : ''
    );

    if ($disabled) {
        echo '<p class="description">';
        echo esc_html__('Currently overridden by PHP constant BEDROCK_ACCESS_KEY_ID.', 'ai-provider-for-bedrock');
        echo '</p>';
    }
}

/**
 * Renders the AWS Secret Access Key field.
 *
 * @since 1.0.0
 *
 * @return void
 */
function render_secret_key_field(): void
{
    $value    = get_option('bedrock_ai_secret_access_key', '');
    $disabled = defined('BEDROCK_SECRET_ACCESS_KEY');

    printf(
        '<input type="password" id="bedrock_ai_secret_access_key" name="bedrock_ai_secret_access_key"'
        . ' value="%s" class="regular-text" %s />',
        esc_attr(is_string($value) ? $value : ''),
        $disabled ? 'disabled="disabled"' : ''
    );

    if ($disabled) {
        echo '<p class="description">';
        echo esc_html__('Currently overridden by PHP constant BEDROCK_SECRET_ACCESS_KEY.', 'ai-provider-for-bedrock');
        echo '</p>';
    }
}

/**
 * Renders the AWS Region select field.
 *
 * @since 1.0.0
 *
 * @return void
 */
function render_region_field(): void
{
    $value    = get_option('bedrock_ai_region', 'eu-north-1');
    $value    = is_string($value) ? $value : 'eu-north-1';
    $disabled = defined('BEDROCK_REGION');
    $regions  = get_allowed_regions();

    printf(
        '<select id="bedrock_ai_region" name="bedrock_ai_region" %s>',
        $disabled ? 'disabled="disabled"' : ''
    );

    foreach ($regions as $region) {
        printf(
            '<option value="%s" %s>%s</option>',
            esc_attr($region),
            selected($value, $region, false),
            esc_html($region)
        );
    }

    echo '</select>';

    if ($disabled) {
        echo '<p class="description">';
        echo esc_html__('Currently overridden by PHP constant BEDROCK_REGION.', 'ai-provider-for-bedrock');
        echo '</p>';
    }
}
