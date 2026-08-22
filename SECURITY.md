# Security Policy

## Supported Versions

| Version | Supported          |
|---------|--------------------|
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

We take security seriously at FoPost. If you discover a security vulnerability in this package, please report it responsibly.

### How to Report

**Please do NOT open a public GitHub issue for security vulnerabilities.**

Instead, report it privately through [https://fopost.com/contact](https://fopost.com/contact) with:

- A description of the vulnerability
- Steps to reproduce the issue
- The potential impact
- Any suggested fix (if you have one)

### What to Expect

- **Acknowledgment:** We will acknowledge your report within 48 hours.
- **Assessment:** We will assess the severity and impact within 5 business days.
- **Fix:** We will work on a fix and coordinate a release timeline with you.
- **Credit:** We will credit you in the release notes (unless you prefer to remain anonymous).

### Scope

This security policy covers:

- The `fopost/social-core` PHP package
- OAuth token handling and storage contracts
- HTTP client and API communication
- Content validation and sanitization
- Any code in the `src/` directory

### Out of Scope

- Vulnerabilities in third-party dependencies (please report those upstream)
- Vulnerabilities in framework-specific packages (fopost-social-laravel, fopost-social-wp) — those have their own security policies
- Issues that require physical access to the server

## Security Best Practices

When using FoPost Social Core:

- **Never** commit API tokens or OAuth credentials to version control.
- **Always** use HTTPS endpoints for API communication.
- **Always** keep SSL verification enabled in production (`verifySsl: true`).
- **Store** tokens securely using the `TokenStoreInterface` with encryption.
- **Rotate** API tokens and OAuth credentials regularly.
- **Use** environment variables or secure vaults for sensitive configuration.
