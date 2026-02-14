# Owlstack Core — Examples

This directory contains two sets of examples demonstrating how to use the Owlstack Core library.

## Directory Structure

```
examples/
├── mock/              ← Safe to run, no API keys needed
│   ├── 01_creating_posts.php
│   ├── 02_media_handling.php
│   ├── ...
│   ├── platform_discord.php
│   ├── platform_slack.php
│   └── ...
└── real/              ← Sends real data to real APIs
    ├── .env.example
    ├── helpers.php
    ├── platform_telegram.php
    ├── platform_twitter.php
    └── ...
```

## Mock Examples (`mock/`)

Use **mock HTTP clients** that simulate API responses locally. No accounts, API keys, or internet connection required. Safe to run anytime.

```bash
# Run a single mock example
php examples/mock/platform_slack.php

# Run all mock examples
for f in examples/mock/*.php; do php "$f"; done
```

### What's included

| File | Topic |
|:-----|:------|
| `01_creating_posts.php` | Creating `Post` objects with various options |
| `02_media_handling.php` | `Media` and `MediaCollection` usage |
| `03_formatting.php` | Platform formatters, truncation, hashtags |
| `04_platform_config.php` | `PlatformCredentials`, `OwlstackConfig`, validation |
| `05_platform_registry.php` | Registering and resolving platforms |
| `06_publishing.php` | Full publish flow with `Publisher` |
| `07_error_handling.php` | Exception types and handling |
| `08_events.php` | Event dispatch pipeline |
| `09_support_utilities.php` | `Arr`, `Str`, `Clock` helpers |
| `10_auth_oauth.php` | OAuth flow with mock provider/store |
| `11_delivery_status.php` | `DeliveryStatus` enum lifecycle |
| `12_full_workflow.php` | End-to-end multi-platform publishing |
| `platform_*.php` | Platform-specific deep dives (Discord, Instagram, Pinterest, Reddit, Slack, Tumblr, WhatsApp) |

## Real API Examples (`real/`)

Use the **real HTTP client** and send actual requests to social media APIs. Requires real credentials.

```bash
# 1. Set your credentials
export SLACK_BOT_TOKEN=xoxb-your-real-token
export SLACK_CHANNEL=C0123GENERAL

# 2. Run the example
php examples/real/platform_slack.php
```

See [real/README.md](real/README.md) for full setup instructions and credential requirements for all 11 platforms.

### What's included

One example per platform — validates credentials, publishes a test post, and displays platform constraints:

| File | Platform |
|:-----|:---------|
| `platform_telegram.php` | Telegram Bot API |
| `platform_twitter.php` | Twitter/X API v2 |
| `platform_facebook.php` | Facebook Graph API |
| `platform_linkedin.php` | LinkedIn API |
| `platform_discord.php` | Discord Bot / Webhook |
| `platform_instagram.php` | Instagram Content Publishing API |
| `platform_pinterest.php` | Pinterest API v5 |
| `platform_reddit.php` | Reddit API |
| `platform_slack.php` | Slack Web API / Webhook |
| `platform_tumblr.php` | Tumblr API v2 |
| `platform_whatsapp.php` | WhatsApp Cloud API |

> **Warning:** Real examples will post actual content to your accounts. Some platforms may have rate limits or costs associated with API usage.
