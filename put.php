<?php

// Directory to store JSON files
$data_dir = __DIR__ . '/data';

// Directory where API token
// will be stored
$token_dir = __DIR__ . '/token';

// Read JSON
$json = file_get_contents('php://input');

// Check for valid API key
if (!isset($_GET['token']) || !file_exists($token_dir . '/' . preg_replace('/[^\da-z]/i', '', $_GET['token']))) {
  header('Status: 403 Forbidden');
  error_log('API token missing!');
  exit(2);
}

// Decode JSON to array
$json_decoded = json_decode($json, true);

// Merged JSON array
$json_merged = array();

// Store only if conditions given
if (empty($json)) {
  header('Status: 400 Bad Request');
  error_log('Missing JSON');
  exit(2);
}

// Check for invalid JSON
if (json_last_error() !== JSON_ERROR_NONE) {
  header('Status: 400 Bad Request');
  error_log('Invalid JSON');
  exit(2);
}

// Check if uuid is set in JSON
if (!isset($json_decoded['uuid'])) {
  header('Status: 400 Bad Request');
  error_log('Missing uuid in JSON');
  exit(2);
}

// Check for valid UUID
if (!ctype_xdigit($json_decoded['uuid'])) {
  header('Status: 400 Bad Request');
  error_log('UUID is not valid');
  exit(2);
}

// Store JSON
file_put_contents($data_dir . '/' . preg_replace('/[^\da-z]/i', '', substr($json_decoded['uuid'], 0, 30)) . '.json', $json);

// Clean up old files
foreach(glob($data_dir . '/*') as $file) {
  if (basename($file) == 'merged.json')
    continue;

  if (filemtime($file) < strtotime('-8 hours')) {
    unlink($file);
    continue;
  }

  $json_merged[] = json_decode(file_get_contents($file));
}

file_put_contents($data_dir . '/merged.json', json_encode($json_merged));

?>
