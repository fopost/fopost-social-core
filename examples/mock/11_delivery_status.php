<?php
// phpcs:disable WordPress,Universal.Operators.DisallowShortTernary -- Example files for framework-agnostic library.

declare(strict_types=1);

/**
 * Example 11: Delivery Status Enum
 *
 * The DeliveryStatus enum represents the lifecycle of a content delivery.
 * Framework packages use it to track publishing progress in their storage.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Fopost\Social\Delivery\DeliveryStatus;

echo "=== Example 11: Delivery Status ===\n\n";

// ── 1. All status values ────────────────────────────────────────────────
echo "1) All statuses\n";
foreach (DeliveryStatus::cases() as $status) {
    echo "   {$status->name} → '{$status->value}'\n";
}
echo "\n";

// ── 2. Creating from value ──────────────────────────────────────────────
echo "2) From stored string\n";
$fromDb = DeliveryStatus::from('published');
echo "   DeliveryStatus::from('published') → {$fromDb->name}\n";

$tryInvalid = DeliveryStatus::tryFrom('unknown');
echo "   DeliveryStatus::tryFrom('unknown') → " . ($tryInvalid === null ? 'null' : $tryInvalid->name) . "\n\n";

// ── 3. Comparison ───────────────────────────────────────────────────────
echo "3) Comparison\n";
$status = DeliveryStatus::Publishing;
echo "   \$status === Publishing : " . ($status === DeliveryStatus::Publishing ? 'true' : 'false') . "\n";
echo "   \$status === Failed     : " . ($status === DeliveryStatus::Failed ? 'true' : 'false') . "\n\n";

// ── 4. Simulated delivery lifecycle ─────────────────────────────────────
echo "4) Simulated lifecycle\n";
$lifecycle = [
    DeliveryStatus::Pending,
    DeliveryStatus::Publishing,
    DeliveryStatus::Published,
];

foreach ($lifecycle as $step) {
    echo "   → {$step->value}\n";
}
echo "\n";

// Error path
echo "   Error path:\n";
$errorPath = [
    DeliveryStatus::Pending,
    DeliveryStatus::Publishing,
    DeliveryStatus::Failed,
];

foreach ($errorPath as $step) {
    echo "   → {$step->value}\n";
}

echo "\n=== Done ===\n";
