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


/*
This script receives reports (in YAML or JSON format) from Gatemon clients via HTTP POST,
and stores them in various ways:
- as separate JSON files (one per Gatemon) in data/ directory
- as a single merged JSON file (data/merged.json), for use by the web frontend
- optionally in an InfluxDB, for use by monitoring tools

Also, whenever a report is received, all outdated data files will be removed.
*/


/**
 * Checks the report received from Gatemon client (in YAML or JSON format),
 * and returns the reported data in internal format.
 */
function sanitizeYamlJsonInput ($report_decoded) {
  ksort_recursive($report_decoded);

  // Check if uuid is set in report
  if (!isset($report_decoded['uuid']))
    throw new Exception('Missing UUID in report');

  // Check for valid UUID
  if (!ctype_xdigit($report_decoded['uuid']))
    throw new Exception('UUID (' . $report_decoded['uuid'] . ') is not valid');

  // Check for time deviation larger 1 minute
  if (abs(strtotime($report_decoded['lastupdated']) - time()) > 90) {
    throw new Exception('Node date deviation too large (UUID: ' . $report_decoded['uuid'] . ')');
  }

  // Create sanitized report structure (using internal format)
  $newReport = array(
    'version' => $report_decoded['version'] ?? '0.1',
    // Overwrite lastupdated with servers time to make timestamps comparable
    'lastupdated' => date(DateTime::ISO8601),
    'uuid' => $report_decoded['uuid'],
    'name' => $report_decoded['name'] ?? 'unknown',
    'provider' => $report_decoded['provider'] ?? 'unknown',
    'vpn-servers' => array(),
  );

  // Copy reported data for each server
  foreach ($report_decoded['vpn-servers'] as $reportedServerData) {
    $newServerData = array(
      'name' => $reportedServerData['name'] ?? 'unknown',
      'status' => array(),
    );

    foreach (array('addresses', 'dns', 'ntp', 'uplink') as $topic) {
      foreach (array('ipv4', 'ipv6') as $addrType) {
        $value = boolval($reportedServerData[$topic][0][$addrType]);
        $newServerData['status'][$topic][$addrType]['up'] = $value;
      }
    }

    $newReport['vpn-servers'][] = $newServerData;
  }
  return $newReport;
}

// Include helper classes
require_once __DIR__ . '/InfluxUploader.php';
require_once __DIR__ . '/ksort_recursive.php';

// Directory to store JSON files
$data_dir = __DIR__ . '/data';

// Directory where API token
// will be stored
$token_dir = __DIR__ . '/token';

// Initialize array for decoded reports
$report_decoded = array();

// Merged JSON array
$json_merged = array();

// Optional configuration file
$config_file = __DIR__ . '/config.ini';

// Read report
$report_in = file_get_contents('php://input');

// Check for valid API key
if (!isset($_GET['token']) || !file_exists($token_dir . '/' . preg_replace('/[^\da-z]/i', '', $_GET['token']))) {
  http_response_code(403);
  error_log('API token missing!');
  exit(2);
}

// Set default values for config
$config = array('influxdb' => array('enabled' => false));

// Load config file if exists
if (file_exists($config_file))
  $config = parse_ini_file($config_file, TRUE);

// Store only if conditions given
if (empty($report_in)) {
  http_response_code(400);
  error_log('Missing report');
  exit(2);
}

// Decode yaml or json to array
$yaml_decoded = yaml_parse($report_in);

if (!isset($yaml_decoded[0]['uuid'])) {
  $report_decoded = json_decode($report_in, true);

  // Check for invalid JSON
  if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    error_log('Invalid JSON');
    exit(2);
  }
} else
  $report_decoded = $yaml_decoded[0];

try {
  $internal_report = sanitizeYamlJsonInput($report_decoded);
} catch (Exception $e) {
  http_response_code(400);
  error_log('Invalid input (' . $e->getMessage() . '); token=' . $_GET['token']);
  exit(2);
}

// Store JSON
file_put_contents($data_dir . '/' . preg_replace('/[^\da-z]/i', '', substr($internal_report['uuid'], 0, 30)) . '.json', json_encode($internal_report));

// Upload to statistics database
uploadToInfluxDB($internal_report, $config['influxdb']);

// Clean up old files and sum up results
foreach(glob($data_dir . '/*') as $file) {
  if (basename($file) == 'merged.json')
    continue;

  if (filemtime($file) < strtotime('-8 hours')) {
    unlink($file);
    continue;
  }

  $json = json_decode(file_get_contents($file));
  $json_merged[] = $json;
}

file_put_contents($data_dir . '/merged.json', json_encode($json_merged));
