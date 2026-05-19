<?php
$path1 = __DIR__ . '/resources/views/admin/users/create.blade.php';
$path2 = __DIR__ . '/resources/views/admin/users/edit.blade.php';

foreach ([$path1, $path2] as $path) {
    if (!file_exists($path)) { echo "File not found: $path\n"; continue; }
    $content = file_get_contents($path);
    
    // Check if file starts and ends with double quote (")
    if (strlen($content) > 2 && $content[0] === '"' && $content[strlen($content)-1] === '"') {
        // Remove first and last quote
        $clean = substr($content, 1, -1);
        file_put_contents($path, $clean);
        echo "Fixed: $path\n";
    } elseif (preg_match('/^"@extends\\(/', $content)) {
        // Alternative check - starts with quote then @extends
        $clean = substr($content, 1);
        $clean = rtrim($clean, '"');
        file_put_contents($path, $clean);
        echo "Fixed (alt): $path\n";
    } else {
        echo "No outer quotes found: $path\n";
    }
    
    echo "First 30 chars: " . substr(file_get_contents($path), 0, 30) . "\n";
}
echo "Done\n";