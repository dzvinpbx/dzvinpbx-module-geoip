<?php
// Checks that every Messages/*.php file returns an array and that
// Messages/uk.php has exactly the same key set as Messages/en.php.

$dir = ($argv[1] ?? 'Messages');
$files = glob($dir . '/*.php');
if (empty($files)) {
    fwrite(STDERR, "No message files found in $dir\n");
    exit(1);
}

$errors = 0;
$loaded = [];
foreach ($files as $file) {
    $data = (static function (string $f) {
        return include $f;
    })($file);
    if (!is_array($data)) {
        fwrite(STDERR, "$file: does not return an array\n");
        $errors++;
        continue;
    }
    $loaded[basename($file, '.php')] = $data;
}

if (!isset($loaded['en'], $loaded['uk'])) {
    fwrite(STDERR, "en.php or uk.php is missing or invalid\n");
    exit(1);
}

$missing = array_diff(array_keys($loaded['en']), array_keys($loaded['uk']));
$extra   = array_diff(array_keys($loaded['uk']), array_keys($loaded['en']));
foreach ($missing as $key) {
    fwrite(STDERR, "uk.php: missing key '$key'\n");
    $errors++;
}
foreach ($extra as $key) {
    fwrite(STDERR, "uk.php: key '$key' is not present in en.php\n");
    $errors++;
}

if ($errors > 0) {
    fwrite(STDERR, "$errors problem(s) found\n");
    exit(1);
}
echo 'OK: ' . count($files) . ' message files, uk.php matches en.php (' . count($loaded['en']) . " keys)\n";
