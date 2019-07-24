<?php

/*
2017, Oliver Gerlich <oliver.gerlich@gmx.de>.
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
This module uploads Gatemon results to InfluxDB.
*/


/// Escapes string values so they can be sent to InfluxDB (according to the Line Protocol specification)
function escapeInfluxTagValue($str) {
  return addcslashes($str, ",= ");
}


/**
 * Uploads the reported results from one Gatemon client to InfluxDB.
 * InfluxDB is accessed using its HTTP POST API and the InfluxDB Line Protocol.
 *
 * @param $report The parsed results reported by a single Gatemon.
 *   Must be an array with the following entries:
 *   - name: string
 *   - uuid: string
 *   - provider: string
 *   - vpn-servers: array, each entry consisting of:
 *     - name: string
 *     - status: array
 *       - addresses: array, with two entries:
 *         - ipv4: array, with one entry:
 *           - up: boolean
 *         - ipv6: array, with one entry:
 *           - up: boolean
 *       - dns: same format as "addresses"
 *       - ntp: same format as "addresses"
 *       - uplink: same format as "addresses"
 *
 * @param $influxConfig Configuration settings for connecting to InfluxDB.
 *   Must be an array with the following keys:
 *   - enabled: boolean value indicating whether data should be uploaded to InfluxDB at all
 *   - url: URL of the InfluxDB's "write" endpoint
 *   - username: user name to access InfluxDB
 *   - username: password to access InfluxDB
 *   - timeout: timeout in seconds for the HTTP POST request
 */
function uploadToInfluxDB($report, $influxConfig) {
  if (!$influxConfig['enabled']) {
    return;
  }

  $uploadText = '';
  $gatemonId = escapeInfluxTagValue($report['uuid']);
  $gatemonName = escapeInfluxTagValue($report['name']);
  $gatemonProvider = escapeInfluxTagValue($report['provider']);

  foreach ($report['vpn-servers'] as $serverState) {
    $serverName = escapeInfluxTagValue($serverState['name']);
    $uploadText .= "gatemon,server=$serverName,gatemon=$gatemonId,gatemon_name=$gatemonName,gatemon_provider=$gatemonProvider ";
    foreach (array('ntp', 'addresses', 'dns', 'uplink') as $topic) {
      foreach (array('ipv4', 'ipv6') as $addrType) {
        $statusValue = '0.0';
        if ($serverState['status'][$topic][$addrType]['up']) {
          $statusValue = '1.0';
        }
        $uploadText .= "${topic}.$addrType=$statusValue,";
        if ($serverState['status'][$topic][$addrType]['time']) {
          $timeValue = floatval($serverState['status'][$topic][$addrType]['time']);
          $uploadText .= "${topic}.$addrType.time=$timeValue,";
        }
      }
    }
    $uploadText = rtrim($uploadText, ",");
    $uploadText .= "\n";
  }

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $influxConfig['url']);
  curl_setopt($ch, CURLOPT_TIMEOUT, $influxConfig['timeout']);
  curl_setopt($ch, CURLOPT_USERPWD, $influxConfig['username'] . ':' . $influxConfig['password']);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $uploadText);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FAILONERROR, true);

  $server_output = curl_exec($ch);
  if (curl_error($ch)) {
    error_log("InfluxDB upload failed: " . curl_error($ch) . ".");
  }
  curl_close ($ch);
}
