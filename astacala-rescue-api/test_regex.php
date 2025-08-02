<?php

// Test regex patterns to find the problematic one
$patterns = [
    '/\.\.\//i',
    '/\.\.[\\\\]/i',
    '/%2e%2e%2f/i',
    '/%2e%2e%5c/i',
    '/\.\.\%2f/i',
    '/etc\/passwd/i',
    '/windows\/system32/i',
];

$testString = "test string";

foreach ($patterns as $index => $pattern) {
    echo "Testing pattern $index: $pattern\n";
    try {
        $result = preg_match($pattern, $testString);
        echo "Pattern $index: OK\n";
    } catch (Exception $e) {
        echo "Pattern $index: ERROR - " . $e->getMessage() . "\n";
    }
}
