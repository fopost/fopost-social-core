<?php

/**
 * LinkedIn — Real API Example
 *
 * Posts a real message to a LinkedIn personal profile or company page.
 *
 * Required env vars:
 *   LINKEDIN_ACCESS_TOKEN     — OAuth 2.0 access token
 *   LINKEDIN_PERSON_ID        — Your LinkedIn person ID (for personal profile)
 *
 * Optional env vars:
 *   LINKEDIN_ORGANIZATION_ID  — Organization ID (use instead of PERSON_ID for company pages)
 *
 * Usage:
 *   export LINKEDIN_ACCESS_TOKEN=...
 *   export LINKEDIN_PERSON_ID=...
 *   php examples/real/platform_linkedin.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/helpers.php';

use Owlstack\Core\Config\PlatformCredentials;
use Owlstack\Core\Content\Post;
use Owlstack\Core\Http\HttpClient;
use Owlstack\Core\Platforms\LinkedIn\LinkedInFormatter;
use Owlstack\Core\Platforms\LinkedIn\LinkedInPlatform;

// -- Load credentials ---------------------------------------------------------

$accessToken    = requireEnv('LINKEDIN_ACCESS_TOKEN');
$organizationId = optionalEnv('LINKEDIN_ORGANIZATION_ID');
$personId       = optionalEnv('LINKEDIN_PERSON_ID');

if ($organizationId === '' && $personId === '') {
    echo "\n  ✗ Set either LINKEDIN_PERSON_ID or LINKEDIN_ORGANIZATION_ID.\n";
    echo "    See .env.example for details.\n\n";
    exit(1);
}

echo "=== LinkedIn — Real API Example ===\n\n";

// -- Set up platform ----------------------------------------------------------

$credentialData = ['access_token' => $accessToken];

if ($organizationId !== '') {
    $credentialData['organization_id'] = $organizationId;
    echo "  Mode: Company Page (organization_id: {$organizationId})\n\n";
} else {
    $credentialData['person_id'] = $personId;
    echo "  Mode: Personal Profile (person_id: {$personId})\n\n";
}

$credentials = new PlatformCredentials('linkedin', $credentialData);

$httpClient = new HttpClient();
$formatter  = new LinkedInFormatter();
$linkedin   = new LinkedInPlatform($credentials, $httpClient, $formatter);

// -- Validate credentials -----------------------------------------------------

echo "  Validating credentials...\n";
$valid = $linkedin->validateCredentials();
echo "  Credentials valid: " . ($valid ? 'yes' : 'no') . "\n\n";

if (! $valid) {
    echo "  ✗ Cannot proceed with invalid credentials.\n";
    exit(1);
}

// -- Publish a test post ------------------------------------------------------

echo "  Publishing test post to LinkedIn...\n";

$post = new Post(
    title: 'Hello from Owlstack!',
    body: 'This is a real test post published to LinkedIn via the Owlstack Core library. 🦉',
    url: 'https://owlstack.dev',
    tags: ['owlstack', 'linkedin', 'php'],
);

$result = $linkedin->publish($post);
printResult($result);

// -- Show constraints ---------------------------------------------------------

echo "\n  Platform constraints:\n";
printConstraints($linkedin->constraints());

echo "\nDone.\n";
