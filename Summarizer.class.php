<?php

// Class for calculating summarized results from raw monitoring data
class Summarizer {
  const GOOD_PERCENT_THRESHOLD = 65;

  private $overallServerState = array();
  private $numMonitors = 0;
  private $numValues = 0;
  private $numValuesGood = 0;

  public function addMonitorResults($json) {
    // sum up total values for each monitored item:
    $this->numMonitors++;

    foreach ($json->{'vpn-servers'} as $serverState) {
      $serverName = $serverState->{'name'};

      if (!array_key_exists($serverName, $this->overallServerState))
        $this->overallServerState{$serverName} = array();

      foreach (array('ntp', 'addresses', 'dns', 'uplink') as $topic) {
        if (!array_key_exists($topic, $this->overallServerState{$serverName}))
          $this->overallServerState{$serverName}{$topic} = array();

        foreach (array('ipv4', 'ipv6') as $addrType) {
          if (!array_key_exists($addrType, $this->overallServerState{$serverName}{$topic}))
            $this->overallServerState{$serverName}{$topic}{$addrType} = array('good' => 0, 'bad' => NULL, 'percent-good' => NULL);

          $this->numValues++;

          if ($serverState->{$topic}[0]->{$addrType}) {
            $this->overallServerState{$serverName}{$topic}{$addrType}{'good'}++;
            $this->numValuesGood++;
          }
        }
      }
    }
  }

  public function getSummary() {
    // calculate percentages etc. for each monitored item:
    $numTopics = 0;
    $numTopicsGood = 0;

    foreach ($this->overallServerState as &$serverState) {
      $numServerValues = 0;
      $numServerValuesGood = 0;
      $numServerTopics = 0;
      $numServerTopicsGood = 0;

      foreach ($serverState as &$topicState) {
        foreach ($topicState as &$addrTypeState) {
          $addrTypeState{'bad'} = $this->numMonitors - $addrTypeState{'good'};
          $addrTypeState{'percent-good'} = intval(($addrTypeState{'good'} / $this->numMonitors) * 100);
          $numTopics++;
          $numServerValues+=$this->numMonitors;
          $numServerValuesGood+=$addrTypeState{'good'};
          $numServerTopics++;

          if ($addrTypeState{'percent-good'} >= self::GOOD_PERCENT_THRESHOLD) {
            $numTopicsGood++;
            $numServerTopicsGood++;
          }
        }
      }

      $serverState{'summary'} = array(
        'values' => array(
          'good' => $numServerValuesGood,
          'bad' => $numServerValues - $numServerValuesGood,
          'percent-good' => intval(($numServerValuesGood / $numServerValues) * 100),
        ),
        'topics' => array(
          'good' => $numServerTopicsGood,
          'bad' => $numServerTopics - $numServerTopicsGood,
          'percent-good' => intval(($numServerTopicsGood / $numServerTopics) * 100),
        ),
      );
    }

    $overall_state = array(
      'summary' => array(
        'values' => array(
          'good' => $this->numValuesGood,
          'bad' => $this->numValues - $this->numValuesGood,
          'percent-good' => intval(($this->numValuesGood / $this->numValues) * 100),
        ),
        'topics' => array(
          'good' => $numTopicsGood,
          'bad' => $numTopics - $numTopicsGood,
          'percent-good' => intval(($numTopicsGood / $numTopics) * 100),
        ),
      ),
      'vpn-servers' => $this->overallServerState,
    );

    return $overall_state;
  }
}

?>
