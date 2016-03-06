<?php

/*
2016, mortzu <mr@planetcyborg.de>.
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
