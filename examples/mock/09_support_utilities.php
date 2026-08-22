<?php
// phpcs:disable WordPress,Universal.Operators.DisallowShortTernary -- Example files for framework-agnostic library.

declare(strict_types=1);

/**
 * Example 09: Support Utilities
 *
 * Demonstrates the Arr, Str, and Clock helpers.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Fopost\Social\Support\Arr;
use Fopost\Social\Support\Clock;
use Fopost\Social\Support\Str;

echo "=== Example 09: Support Utilities ===\n\n";

// ── 1. Arr — array helpers ──────────────────────────────────────────────
echo "1) Arr\n";

$nested = [
    'database' => [
        'host' => 'localhost',
        'credentials' => [
            'user' => 'root',
            'pass' => 'secret',
        ],
    ],
    'debug' => true,
];

echo "   Arr::get top level  : " . var_export(Arr::get($nested, 'debug'), true) . "\n";
echo "   Arr::get nested     : " . Arr::get($nested, 'database.host') . "\n";
echo "   Arr::get deep nested: " . Arr::get($nested, 'database.credentials.user') . "\n";
echo "   Arr::get missing    : " . var_export(Arr::get($nested, 'database.port', 3306), true) . "\n";

$dirty = ['a' => 1, 'b' => null, 'c' => '', 'd' => 0, 'e' => 'hello'];
echo "   Arr::filterEmpty    : " . json_encode(Arr::filterEmpty($dirty)) . "\n";

$full = ['name' => 'Jamie', 'email' => 'jamie@example.com', 'role' => 'admin', 'age' => 30];
echo "   Arr::only           : " . json_encode(Arr::only($full, ['name', 'email'])) . "\n\n";

// ── 2. Str — string helpers ────────────────────────────────────────────
echo "2) Str\n";

$long = 'This is a very long string that will be limited to a reasonable length for display purposes.';
echo "   Str::limit(100)  : " . Str::limit($long, 100) . "\n";
echo "   Str::limit(40)   : " . Str::limit($long, 40) . "\n";
echo "   Str::limit('...'): " . Str::limit($long, 40, '...') . "\n";
echo "   Str::limit short : " . Str::limit('Short', 40) . "\n";

echo "   Str::slug         : " . Str::slug('Hello World 2026!') . "\n";
echo "   Str::slug(_)      : " . Str::slug('Hello World 2026!', '_') . "\n";
echo "   Str::startsWith   : " . (Str::startsWith('https://example.com', 'https') ? 'true' : 'false') . "\n";
echo "   Str::startsWith   : " . (Str::startsWith('http://example.com', 'https') ? 'true' : 'false') . "\n\n";

// ── 3. Clock — testable time abstraction ───────────────────────────────
echo "3) Clock\n";

echo "   Now        : " . Clock::now()->format('Y-m-d H:i:s') . "\n";
echo "   Timestamp  : " . Clock::timestamp() . "\n";

// Freeze time
$frozen = new DateTimeImmutable('2026-01-01 00:00:00');
Clock::freeze($frozen);
echo "   Frozen at  : " . Clock::now()->format('Y-m-d H:i:s') . "\n";
echo "   Timestamp  : " . Clock::timestamp() . "\n";

// With timezone
$tz = new DateTimeZone('Asia/Tehran');
echo "   Tehran time: " . Clock::now($tz)->format('Y-m-d H:i:s T') . "\n";

// Unfreeze
Clock::unfreeze();
echo "   Unfrozen   : " . Clock::now()->format('Y-m-d H:i:s') . " (live)\n\n";

// Freeze without argument
Clock::freeze();
$t1 = Clock::now()->format('H:i:s.u');
usleep(50_000); // 50ms
$t2 = Clock::now()->format('H:i:s.u');
echo "   freeze() without arg — t1={$t1}, t2={$t2}, same=" . ($t1 === $t2 ? 'yes' : 'no') . "\n";
Clock::unfreeze();

echo "\n=== Done ===\n";
