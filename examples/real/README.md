# Real API Examples

These examples make **real API calls** to social media platforms. They will post actual content to your accounts.

## Prerequisites

1. **PHP 8.1+** with `ext-curl` and `ext-json`
2. **Install dependencies:** `composer install` in the `owlstack-core` root
3. **API credentials** for the platform(s) you want to test

## Quick Start

```bash
# 1. Copy the environment template
cp examples/real/.env.example examples/real/.env

# 2. Fill in your credentials
nano examples/real/.env     # or use your preferred editor

# 3. Export the variables
source examples/real/.env

# 4. Run an example
php examples/real/platform_slack.php
```

> **Note:** Since Owlstack Core is zero-dependency, `.env` files are not auto-loaded. You must `source` the file or `export` variables manually before running examples.

## Credential Reference

### Telegram

```bash
export TELEGRAM_API_TOKEN=123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11
export TELEGRAM_CHANNEL=@mychannel
```

**Setup:** Message [@BotFather](https://t.me/botfinder) on Telegram to create a bot and get your API token. Add the bot to your channel as an admin.

### Twitter / X

```bash
export TWITTER_CONSUMER_KEY=your-api-key
export TWITTER_CONSUMER_SECRET=your-api-key-secret
export TWITTER_ACCESS_TOKEN=your-access-token
export TWITTER_ACCESS_TOKEN_SECRET=your-access-token-secret
```

**Setup:** Create a project and app at the [Twitter Developer Portal](https://developer.x.com/en/portal/dashboard). Generate consumer keys and access tokens with **Read and Write** permissions.

### Facebook

```bash
export FACEBOOK_APP_ID=your-app-id
export FACEBOOK_APP_SECRET=your-app-secret
export FACEBOOK_PAGE_ACCESS_TOKEN=your-page-access-token
export FACEBOOK_PAGE_ID=your-page-id
```

**Setup:** Create an app at [Meta for Developers](https://developers.facebook.com/apps/). Generate a Page Access Token with the `pages_manage_posts` permission via the Graph API Explorer.

### LinkedIn

```bash
export LINKEDIN_ACCESS_TOKEN=your-access-token
export LINKEDIN_PERSON_ID=your-person-id
# OR for company pages:
# export LINKEDIN_ORGANIZATION_ID=your-org-id
```

**Setup:** Create an app at [LinkedIn Developers](https://www.linkedin.com/developers/apps). Request the `w_member_social` product for personal posts, or `w_organization_social` for company pages.

### Discord

```bash
# Bot mode
export DISCORD_BOT_TOKEN=your-bot-token
export DISCORD_CHANNEL_ID=your-channel-id

# OR Webhook mode
# export DISCORD_WEBHOOK_URL=https://discord.com/api/webhooks/...
```

**Setup:** Create a bot at the [Discord Developer Portal](https://discord.com/developers/applications). Add it to your server with the `Send Messages` permission. For webhook mode, create an Incoming Webhook in your channel's Integrations settings.

### Instagram

```bash
export INSTAGRAM_ACCESS_TOKEN=your-access-token
export INSTAGRAM_ACCOUNT_ID=your-instagram-account-id
export INSTAGRAM_TEST_IMAGE_URL=https://picsum.photos/1080/1080   # optional
```

**Setup:** Requires a Facebook App with [Instagram Graph API](https://developers.facebook.com/docs/instagram-api/getting-started) access. Your Instagram account must be a Business or Creator account connected to a Facebook Page. Images must be hosted at publicly accessible URLs.

### Pinterest

```bash
export PINTEREST_ACCESS_TOKEN=your-access-token
export PINTEREST_BOARD_ID=your-board-id
```

**Setup:** Create an app at [Pinterest Developers](https://developers.pinterest.com/apps/). Generate an access token with `pins:read` and `pins:write` scopes.

### Reddit

```bash
export REDDIT_CLIENT_ID=your-client-id
export REDDIT_CLIENT_SECRET=your-client-secret
export REDDIT_ACCESS_TOKEN=your-access-token
export REDDIT_USERNAME=your-username
export REDDIT_SUBREDDIT=test
```

**Setup:** Create a **script** type app at [Reddit App Preferences](https://www.reddit.com/prefs/apps). Use the `r/test` subreddit for safe testing — it's specifically designed for bot testing.

### Slack

```bash
# Bot mode
export SLACK_BOT_TOKEN=xoxb-your-bot-token
export SLACK_CHANNEL=C0123GENERAL

# OR Webhook mode
# export SLACK_WEBHOOK_URL=https://hooks.slack.com/services/T00/B00/xxx
```

**Setup:** Create an app at [Slack API](https://api.slack.com/apps). Add the `chat:write` bot scope, install the app to your workspace, and invite the bot to the target channel.

### Tumblr

```bash
export TUMBLR_ACCESS_TOKEN=your-access-token
export TUMBLR_BLOG_IDENTIFIER=myblog.tumblr.com
```

**Setup:** Register an app at [Tumblr OAuth Apps](https://www.tumblr.com/oauth/apps). Use the OAuth 2.0 flow to obtain an access token.

> **Tip:** The Tumblr example publishes as a **draft** by default for safety. Edit the `state` option in the example to `'published'` when you're ready to go live.

### WhatsApp

```bash
export WHATSAPP_ACCESS_TOKEN=your-access-token
export WHATSAPP_PHONE_NUMBER_ID=your-phone-number-id
export WHATSAPP_TO=+1234567890
```

**Setup:** Set up the [WhatsApp Cloud API](https://developers.facebook.com/docs/whatsapp/cloud-api/get-started) through a Meta for Developers app. The recipient must have opted in to receive messages from your business number.

## Safety Tips

- **Use test accounts** when possible (e.g., Reddit's `r/test`, a private Slack channel, a Tumblr draft)
- **Never commit** a filled `.env` file to version control
- **Be mindful of rate limits** — most platforms have API usage quotas
- **Review post content** before changing Tumblr's `state` from `draft` to `published`
- The `.env.example` file with placeholder values is safe to commit

## Troubleshooting

**"Missing required environment variable"** — Make sure you've exported the variable:
```bash
source examples/real/.env
# or
export SLACK_BOT_TOKEN=xoxb-...
```

**"Credentials valid: no"** — Double-check your tokens haven't expired. Some platforms (Twitter, LinkedIn, Instagram) use tokens that expire and need refreshing.

**cURL errors** — Ensure `ext-curl` is enabled in your PHP installation:
```bash
php -m | grep curl
```
