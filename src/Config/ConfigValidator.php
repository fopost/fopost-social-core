<?php

declare(strict_types=1);

namespace Owlstack\Core\Config;

use Owlstack\Core\Exceptions\OwlstackException;

/**
 * Validates that platform configurations have all required credentials.
 */
class ConfigValidator
{
    /**
     * Required credential keys per platform.
     *
     * @var array<string, string[]>
     */
    private array $requiredKeys = [
        'telegram' => ['api_token'],
        'twitter' => ['consumer_key', 'consumer_secret', 'access_token', 'access_token_secret'],
        'facebook' => ['app_id', 'app_secret', 'page_access_token', 'page_id'],
        'reddit' => ['client_id', 'client_secret', 'access_token', 'username'],
        'discord' => ['bot_token', 'channel_id'],
        'slack' => ['bot_token', 'channel'],
        'instagram' => ['access_token', 'instagram_account_id'],
        'pinterest' => ['access_token', 'board_id'],
        'whatsapp' => ['access_token', 'phone_number_id'],
        'tumblr' => ['access_token', 'blog_identifier'],
    ];

    /**
     * Register required keys for a custom platform.
     *
     * @param string   $platform The platform name.
     * @param string[] $keys     Required credential keys.
     */
    public function registerRequiredKeys(string $platform, array $keys): void
    {
        $this->requiredKeys[$platform] = $keys;
    }

    /**
     * Validate credentials for a specific platform.
     *
     * @return string[] Array of missing key names (empty if valid).
     */
    public function validate(PlatformCredentials $credentials): array
    {
        $required = $this->requiredKeys[$credentials->platform] ?? [];
        $missing = [];

        foreach ($required as $key) {
            if (!$credentials->has($key)) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    /**
     * Validate all platforms in a config and throw if any are invalid.
     *
     * @throws OwlstackException If any platform has missing credentials.
     */
    public function validateConfig(OwlstackConfig $config): void
    {
        $errors = [];

        foreach ($config->configuredPlatforms() as $platform) {
            $credentials = $config->credentials($platform);
            $missing = $this->validate($credentials);

            if (!empty($missing)) {
                $errors[$platform] = $missing;
            }
        }

        if (!empty($errors)) {
            $messages = [];
            foreach ($errors as $platform => $keys) {
                $messages[] = "{$platform}: missing " . implode(', ', $keys);
            }

            throw new OwlstackException(
                'Invalid configuration: ' . implode('; ', $messages)
            );
        }
    }
}
