=== AI Provider for Claude on Bedrock ===
Contributors: laurisaarni
Tags: ai, bedrock, claude, artificial-intelligence, connector
Requires at least: 6.9
Tested up to: 7.0
Stable tag: 0.2.0
Requires PHP: 8.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Amazon Bedrock Claude provider for the WordPress AI Client.

== Description ==

This plugin extends the WordPress AI Client to enable Claude model access through the Amazon Bedrock Runtime API. It supports text generation, tool use, function calling, and structured outputs with JSON schemas.

Two authentication methods are supported:

* **Bedrock API key** (simplest): generate one in the Amazon Bedrock console under "API keys". Enter it on the WordPress core connectors settings page, define it as the `BEDROCK_API_KEY` constant, or save it on the plugin's settings page.
* **IAM access key pair**: requests are signed with AWS Signature V4.

This project is independent and is not affiliated with, endorsed by, or sponsored by Amazon.

= Requirements =

* For WordPress 6.9, the [wordpress/php-ai-client](https://github.com/WordPress/php-ai-client) package must be installed
* For WordPress 7.0 and above, no additional changes are required
* AWS account with Amazon Bedrock access and Claude models enabled

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/ai-provider-for-bedrock/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Enter your Bedrock API key on the core connectors settings page, or configure credentials under Settings > Bedrock AI Provider
4. Optionally set the AWS region under Settings > Bedrock AI Provider (defaults to `eu-north-1`)

== Frequently Asked Questions ==

= How do I get a Bedrock API key? =

Open the [Amazon Bedrock console](https://console.aws.amazon.com/bedrock/), select your region, and generate a key under "API keys". Make sure the Claude models you want to use are enabled for your account in that region.

= Can I use IAM credentials instead of an API key? =

Yes. Define `BEDROCK_ACCESS_KEY_ID` and `BEDROCK_SECRET_ACCESS_KEY` constants in `wp-config.php` (or save them on the plugin's settings page) and requests are signed with AWS Signature V4. The IAM user needs the `bedrock:InvokeModel` permission.

= Does this plugin work without the PHP AI Client? =

No, this plugin requires the PHP AI Client, which is bundled in WordPress core since 7.0. On WordPress 6.9 the PHP AI Client plugin must be installed and activated. This plugin provides the Bedrock-specific implementation that the PHP AI Client uses.

== Changelog ==

= 0.2.0 =

* Models are now discovered at runtime from the Amazon Bedrock inference profiles API (cached for one hour), with a hardcoded fallback list
* Geo-specific inference profiles are preferred over global duplicates
* Automatic model selection defaults to a curated known-good model

= 0.1.0 =

* Initial release
* Text generation with Claude models via the Amazon Bedrock Runtime API
* Bedrock API key (Bearer) and AWS Signature V4 authentication
* WordPress core connectors settings page integration
* Tool use, function calling, and structured outputs with JSON schemas
* Cross-region inference profile model IDs derived from the configured region
