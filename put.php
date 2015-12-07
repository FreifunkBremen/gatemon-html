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


// Class for calculating summarized results from raw monitoring data
class Summarizer {
  const GOOD_PERCENT_THRESHOLD = 65;

  private $overallServerState = array();
  private $numMonitors = 0;
  private $numValues = 0;
  private $numValuesGood = 0;

  public function addMonitorResults($json) {
    # sum up total values for each monitored item:
    $this->numMonitors++;

    foreach ($json->{"vpn-servers"} as $serverState) {
      $serverName = $serverState->{"name"};
      if (!array_key_exists($serverName, $this->overallServerState))
        $this->overallServerState{$serverName} = array();

      foreach (array("ntp", "addresses", "dns", "uplink") as $topic) {
        if (!array_key_exists($topic, $this->overallServerState{$serverName}))
          $this->overallServerState{$serverName}{$topic} = array();
        foreach (array("ipv4", "ipv6") as $addrType) {
          if (!array_key_exists($addrType, $this->overallServerState{$serverName}{$topic}))
            $this->overallServerState{$serverName}{$topic}{$addrType} = array("good" => 0, "bad" => NULL, "percent-good" => NULL);
          $this->numValues++;
          if ($serverState->{$topic}[0]->{$addrType}) {
            $this->overallServerState{$serverName}{$topic}{$addrType}{"good"}++;
            $this->numValuesGood++;
          }
        }
      }
    }
  }

  public function getSummary() {
    # calculate percentages etc. for each monitored item:
    $numTopics = 0;
    $numTopicsGood = 0;
    foreach ($this->overallServerState as &$serverState) {
      $numServerValues = 0;
      $numServerValuesGood = 0;
      $numServerTopics = 0;
      $numServerTopicsGood = 0;
      foreach ($serverState as &$topicState) {
        foreach ($topicState as &$addrTypeState) {
          $addrTypeState{"bad"} = $this->numMonitors - $addrTypeState{"good"};
          $addrTypeState{"percent-good"} = intval(($addrTypeState{"good"} / $this->numMonitors) * 100);
          $numTopics++;
          $numServerValues+=$this->numMonitors;
          $numServerValuesGood+=$addrTypeState{"good"};
          $numServerTopics++;
          if ($addrTypeState{"percent-good"} >= self::GOOD_PERCENT_THRESHOLD) {
            $numTopicsGood++;
            $numServerTopicsGood++;
          }
        }
      }

      $serverState{"summary"} = array(
        "values" => array(
          "good" => $numServerValuesGood,
          "bad" => $numServerValues - $numServerValuesGood,
          "percent-good" => intval(($numServerValuesGood / $numServerValues) * 100),
        ),
        "topics" => array(
          "good" => $numServerTopicsGood,
          "bad" => $numServerTopics - $numServerTopicsGood,
          "percent-good" => intval(($numServerTopicsGood / $numServerTopics) * 100),
        ),
      );
    }

    $overallState = array(
      "summary" => array(
        "values" => array(
          "good" => $this->numValuesGood,
          "bad" => $this->numValues - $this->numValuesGood,
          "percent-good" => intval(($this->numValuesGood / $this->numValues) * 100),
        ),
        "topics" => array(
          "good" => $numTopicsGood,
          "bad" => $numTopics - $numTopicsGood,
          "percent-good" => intval(($numTopicsGood / $numTopics) * 100),
        ),
      ),
      "vpn-servers" => $this->overallServerState,
    );

    return $overallState;
  }
}


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

$overallState = $summarizer->getSummary();
file_put_contents($data_dir . '/overall.json', json_encode($overallState));

?>
