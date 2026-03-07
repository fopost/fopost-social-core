# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.1] - 2025-07-25

### Fixed
- Added PHPCS inline directives (`phpcs:disable`) to all source, example, and test files for WordPress Plugin Check compatibility
- Framework-agnostic library files now pass WordPress coding standards when bundled as a vendor dependency in WordPress plugins
- Suppressed `WordPress.Security.EscapeOutput.ExceptionNotEscaped` — exceptions are not WordPress output in this library
- Suppressed `WordPress.WP.AlternativeFunctions` — native PHP functions (cURL, json_encode, file_get_contents) are required for framework independence
- Suppressed `WordPress.PHP.DiscouragedPHPFunctions` — base64_encode/urlencode required for OAuth and API operations
- Suppressed `WordPress.PHP.NoSilencedErrors` — @unlink used for temporary file cleanup in HttpClient
- Suppressed `Universal.Operators.DisallowShortTernary` — short ternary intentionally used for concise null/empty handling

## [1.0.0]

### Added
- Framework-agnostic PHP core for social media publishing
- Platform abstractions with `PlatformInterface` and `PlatformResponseInterface`
- Content model: `Post`, `Media`, `MediaCollection`, and `CanonicalLink` value objects
- Publishing engine with `Publisher` orchestrator and `PublishResult`
- Platform-specific formatters with `FormatterInterface` contract
- Character truncation and hashtag extraction utilities
- Telegram platform implementation (`TelegramPlatform`, `TelegramFormatter`)
- Twitter/X platform implementation (`TwitterPlatform`, `TwitterFormatter`)
- Facebook platform implementation (`FacebookPlatform`, `FacebookFormatter`)
- `PlatformRegistry` for managing multiple platform instances
- OAuth handler with `OAuthProviderInterface` and `TokenStoreInterface` contracts
- `AccessToken` value object with expiration and refresh token support
- Configuration system with `OwlstackConfig`, `PlatformCredentials`, and `ConfigValidator`
- `DeliveryStatus` enum for tracking publish delivery states
- Event system with `EventDispatcherInterface`, `PostPublished`, and `PostFailed` events
- cURL-based HTTP client with zero framework dependencies
- Proxy support with authentication in HTTP client
- Exception hierarchy: `OwlstackException`, `AuthenticationException`, `PlatformException`, `RateLimitException`, `ContentTooLongException`, `MediaValidationException`
- Support utilities: `Arr`, `Str`, `Clock` helpers
- PHPUnit test configuration with Unit and Integration test suites
