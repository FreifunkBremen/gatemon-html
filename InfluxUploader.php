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

function escapeInfluxTagValue($str) {
  return addcslashes($str, ",= ");
}

// Uploads the parsed results from one Gatemon client to InfluxDB
function uploadToInfluxDB($json, $influxConfig) {
  if (!$influxConfig['enabled']) {
    return;
  }

  $uploadText = '';
  $gatemonId = escapeInfluxTagValue($json['uuid']);
  $gatemonName = escapeInfluxTagValue($json['name']);
  $gatemonProvider = escapeInfluxTagValue($json['provider']);

  foreach ($json['vpn-servers'] as $serverState) {
    $serverName = escapeInfluxTagValue($serverState['name']);
    $uploadText .= "gatemon,server=$serverName,gatemon=$gatemonId,gatemon_name=$gatemonName,gatemon_provider=$gatemonProvider ";
    foreach (array('ntp', 'addresses', 'dns', 'uplink') as $topic) {
      foreach (array('ipv4', 'ipv6') as $addrType) {
        $value = '0.0';
        if ($serverState[$topic][0][$addrType]) {
          $value = '1.0';
        }
        $uploadText .= "${topic}.$addrType=$value,";
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
