<?php

/**
 * CSS parity harness (ADR-063) — summarise a compare.cjs report.
 *
 * Usage:
 *   php tests/Support/CssParity/summarize.php <report.json> [--ignore=<prop-regex>] [--top=40]
 *
 * Prints the totals, then every changed element kind with the properties
 * that changed and up to three documents it changed in. Layout properties
 * that only follow from another change (sizes, origins, insets) are folded
 * into a count so the causes stand out.
 */

$report = json_decode((string) @file_get_contents($argv[1] ?? ''), true);
if (!is_array($report)) {
    fwrite(STDERR, "usage: php summarize.php <report.json> [--ignore=<prop-regex>] [--top=40]\n");
    exit(2);
}
$ignore = null;
$top = 40;
foreach (array_slice($argv, 2) as $arg) {
    if (str_starts_with($arg, '--ignore=')) {
        $ignore = '/' . substr($arg, 9) . '/';
    } elseif (str_starts_with($arg, '--top=')) {
        $top = (int) substr($arg, 6);
    }
}
$derived = '/^(block-size|inline-size|height|width|min-(block|inline)-size|min-(height|width)|perspective-origin|transform-origin|inset-[a-z-]+|top|bottom|left|right)$/';

$byElement = [];
foreach ($report['diffs'] as $key => $diff) {
    [$element, $change] = explode(' | ', $key, 2);
    $prop = explode(':', $change, 2)[0];
    if ($ignore && preg_match($ignore, $prop)) {
        continue;
    }
    $element = preg_replace('/::(before|after)$/', '', $element);
    $entry = &$byElement[$element];
    $entry['count'] = ($entry['count'] ?? 0) + $diff['count'];
    if (preg_match($derived, $prop)) {
        $entry['derived'] = ($entry['derived'] ?? 0) + 1;
    } else {
        $entry['props'][$prop] = $change;
    }
    foreach ($diff['docs'] as $doc) {
        $entry['docs'][$doc] = true;
    }
    unset($entry);
}
uasort($byElement, fn ($a, $b) => $b['count'] <=> $a['count']);

printf("%d documents, %d elements/width, %d distinct diffs, %d element kinds\n\n",
    $report['documents'], $report['elements'], count($report['diffs']), count($byElement));
foreach (array_slice($byElement, 0, $top, true) as $element => $entry) {
    $docs = implode(', ', array_slice(array_keys($entry['docs'] ?? []), 0, 3));
    echo str_pad((string) $entry['count'], 7) . " {$element}  ({$docs})\n";
    foreach (array_slice($entry['props'] ?? [], 0, 6) as $change) {
        echo "          {$change}\n";
    }
    if (!empty($entry['derived'])) {
        echo "          + {$entry['derived']} derived layout change(s)\n";
    }
}
