<?php

/*
2016, mortzu <mortzu@gmx.de>.
All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are
permitted provided that the following conditions are met:

* Redistributions of source code must retain the above copyright notice, this list of
  conditions and the following disclaimer.

* Redistributions in binary form must reproduce the above copyright notice, this list
  of conditions and the following disclaimer in the documentation and/or other materials
  provided with the distribution.

* The names of its contributors may not be used to endorse or promote products derived
  from this software without specific prior written permission.

* Feel free to send Club Mate to support the work.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS
OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS
AND CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR
OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
*/

// Include helper classes
require_once __DIR__ . '/Summarizer.class.php';
require_once __DIR__ . '/InfluxUploader.php';

// Directory to store JSON files
$data_dir = __DIR__ . '/data';

// Directory where API token
// will be stored
$token_dir = __DIR__ . '/token';

// Read JSON
$json = file_get_contents('php://input');

// Check for valid API key
if (!isset($_GET['token']) || !file_exists($token_dir . '/' . preg_replace('/[^\da-z]/i', '', $_GET['token']))) {
  http_response_code(403);
  error_log('API token missing!');
  exit(2);
}

$config = parse_ini_file(__DIR__ . "/config.ini", TRUE);

// Decode JSON to array
$json_decoded = json_decode($json, true);

// Merged JSON array
$json_merged = array();

// Store only if conditions given
if (empty($json)) {
  http_response_code(400);
  error_log('Missing JSON');
  exit(2);
}

// Check for invalid JSON
if (json_last_error() !== JSON_ERROR_NONE) {
  http_response_code(400);
  error_log('Invalid JSON');
  exit(2);
}

// Check if uuid is set in JSON
if (!isset($json_decoded['uuid'])) {
  http_response_code(400);
  error_log('Missing UUID in JSON');
  exit(2);
}

// Check for valid UUID
if (!ctype_xdigit($json_decoded['uuid'])) {
  http_response_code(400);
  error_log('UUID is not valid');
  exit(2);
}

// Check for time deviation larger 1 minute
if (abs(strtotime($json_decoded['lastupdated']) - time()) > 90) {
    http_response_code(400);
    error_log('Node date deviation too large');
    exit(2);
}

// Overwrite lastupdated with servers time to make timestamps comparable
$json_decoded['lastupdated'] = time();

// Store JSON
file_put_contents($data_dir . '/' . preg_replace('/[^\da-z]/i', '', substr($json_decoded['uuid'], 0, 30)) . '.json', $json);

// Upload to statistics database
uploadToInfluxDB($json_decoded, $config['influxdb']);

$summarizer = new Summarizer();

// Clean up old files and sum up results
foreach(glob($data_dir . '/*') as $file) {
  if (basename($file) == 'merged.json' || basename($file) == 'overall.json')
    continue;

  if (filemtime($file) < strtotime('-8 hours')) {
    unlink($file);
    continue;
  }

  $json = json_decode(file_get_contents($file));
  $json_merged[] = $json;

  $summarizer->addMonitorResults($json);
}

file_put_contents($data_dir . '/merged.json', json_encode($json_merged));

$overall_state = $summarizer->getSummary();
file_put_contents($data_dir . '/overall.json', json_encode($overall_state));

