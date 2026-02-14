# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
