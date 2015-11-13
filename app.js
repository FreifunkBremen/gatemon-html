$(function() {
  $.ajax({
    url: '/data/merged.json',
    success: parseResponse,
    dataType: 'json'
  });

  function parseResponse(data) {
    jQuery.each(["vpn01", "vpn02", "vpn03", "vpn04", "vpn05", "vpn06"], function(vpnindex, vpnserver) {
      $('<th colspan="6">' + vpnserver + '</th>').appendTo($('#servertable thead tr#vpnserver'));
      $('<td colspan="2">Uplink</td><td colspan="2">RDNSS</td><td colspan="2">NTP</td>').appendTo($('#servertable thead tr#vpnservices'));
      $('<td>IPv4</td><td>IPv6</td><td>IPv4</td><td>IPv6</td><td>IPv4</td><td>IPv6</td>').appendTo($('#servertable tr#vpnservicesfamily'));
    });

    data.forEach(function(meshmon) {
      var tmp_content = '<tr id=' + meshmon['uuid'] + '><td title="Name: ' + meshmon['name'] + '\nProvider: ' + meshmon['provider'] + '\nZuletzt aktualisiert: ' + meshmon['lastupdated'] + '">' + meshmon['uuid'] + '</td>';

      meshmon['vpn-servers'].forEach(function(meshvpnserver) {
        jQuery.each(["uplink", "dns", "ntp"], function(index, value) {
          if (meshvpnserver[value][0]['ipv4']) {
            tmp_content += '<td class="good"></td>';
          } else {
            tmp_content += '<td class="bad"></td>';
          }

          if (meshvpnserver[value][0]['ipv6']) {
            tmp_content += '<td class="good"></td>';
          } else {
            tmp_content += '<td class="bad"></td>';
          }
        });
      });

      $(tmp_content + '</tr>').appendTo($('#servertable tbody'));
    });
  }
});
