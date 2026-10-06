<?php

/** Run on a COPY of the Choice project; does not touch .env, databases, vendor or storage. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = __DIR__;
if (! is_file($root.'/artisan') || ! is_file($root.'/config/scheduler.php')) {
    fwrite(STDERR, "Run from the scheduler project root after extracting the patch.\n");
    exit(1);
}
$manifest = json_decode(file_get_contents($root.'/scheduler-cleanup-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$selected = [];
foreach ($manifest as $entry) {
    $relative = $entry['path'];
    if (str_contains($relative, '..') || str_starts_with($relative, '/') || str_contains($relative, '\\')) {
        throw new RuntimeException('Unsafe manifest path');
    }
    $file = $root.'/'.$relative;
    if (! file_exists($file)) {
        continue;
    }
    if (is_link($file) || ! str_starts_with((string) realpath($file), $root.DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Unsafe target');
    }
    if (hash_file('sha256', $file) !== $entry['sha256']) {
        fwrite(STDERR, 'Refused: locally modified legacy file '.$relative.". Back it up and remove that legacy file in the new project copy, then rerun. No files removed.\n");
        exit(1);
    }
    $selected[] = $file;
}
foreach ($selected as $file) {
    if (! unlink($file)) {
        throw new RuntimeException('Could not remove '.$file);
    }
}
echo 'Removed '.count($selected)." legacy Choice files. Original source and database are unaffected.\n";
