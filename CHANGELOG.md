# Changelog

All notable changes to this project will be documented in this file.

## [0.2.0] - 2026-07-24

### Added
- Runtime model discovery from the Amazon Bedrock inference profiles API, cached for one hour, with a hardcoded fallback list when the API is unreachable
- De-duplication of models available under both geo-specific and global inference profiles, preferring the geo-specific profile for the configured region
- Curated known-good models sort first within each family so automatic model selection defaults to an enabled model

## [0.1.0] - 2026-07-24

### Added
- Initial release
- Text generation with Claude models via the Amazon Bedrock Runtime API
- Bedrock API key (Bearer token) and AWS Signature V4 authentication
- WordPress core connectors settings page integration
- Tool use, function calling, and structured outputs with JSON schemas
- Cross-region inference profile model IDs derived from the configured region
- Admin settings page for credential and region configuration
- PHP constant overrides for containerized deployments
- Unit and integration test suites
- Dist build and deploy tooling
