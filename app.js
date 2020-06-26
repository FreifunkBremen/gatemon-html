function shortenUuid(uuid) {
  if (uuid.length <= 10)
    return uuid;
  else
    return uuid.substr(0,10) + "…";
}

$(function() {
  // Fetch JSON file
  $.ajax({
    url: '/data/merged.json',
    success: parseResponse,
    dataType: 'json'
  });

  $.timeago.settings.allowFuture = true;
  $.timeago.settings.allowPast = true;

  // Parse response
  function parseResponse(data) {
    // Reset counter for array
    gatemon_counter = 0;

    $('<thead>'
      +'<tr>'
      + '<th>Gatemon Name</th>'
      + '<th>Provider</th>'
      + '<th>Node</th>'
      + '<th>Version</th>'
      + '<th>Last Update</time></th>'
      + '</tr>'
      + '</thead>').appendTo($('#lastupdated'));

    // Gatemon reports older than 2 hours are marked as bad:
    var oldestAllowedTimestamp = Date.now() - (2*60*60*1000);

    // Iterate over gatemons
    data.forEach(function(gatemon) {
      // Increment counter
      gatemon_counter++;

      gatemon_class = "";
      if (Date.parse(gatemon['lastupdated']) < oldestAllowedTimestamp)
        gatemon_class = "outdated";

      $('<tr>'
        + '<td>' + gatemon['name'] + '</td>'
        + '<td>' + gatemon['provider'] + '</td>'
        + '<td>' + ((gatemon['node-id'] != 'unknown' && gatemon['node-id'] != undefined) ? '<a href="https://map.bremen.freifunk.net/#!/map/' + gatemon['node-id'] + '">' + gatemon['node-hostname'] + '</a></td>' : '')
        + '<td>' + gatemon['version'] + '</td>'
        + '<td class="' + gatemon_class + '">'
        + '<time class="timeago" datetime="' + gatemon['lastupdated'] + '">' + gatemon['lastupdated'] + '</time>'
        + '</td>'
        + '</tr>').appendTo($('#lastupdated'));
      $(".timeago").timeago();

      // Iterate over gatemon data
      gatemon['vpn-servers'].forEach(function(vpnserver_data) {
        // Short VPN hostname
        vpnserver_name = vpnserver_data['name'].split('.')[0];

        // Check if an element with ID of vpnserver exists
        if ($("#" + vpnserver_name).length == 0) {
          $('<div class="col-lg-6 col-md-12"><div class="well">'
            + '<table class="table" id="' + vpnserver_name + '">'
            + '<thead>'
            + '<tr id="' + vpnserver_name + 'server"></tr>'
            + '<tr id="' + vpnserver_name + 'services"><td></td></tr>'
            + '<tr id="' + vpnserver_name + 'servicesfamily"><td></td></tr>'
            + '</thead>'
            + '<tbody></tbody>'
            + '</table></div></div>').appendTo($('#content'));
        }

        $('<tr id="' + vpnserver_name + gatemon['uuid'] + '">'
          + '<td class="' + gatemon_class + '" title="'
          + 'Name: ' + gatemon['name'] + '\n'
          + 'Provider: ' + gatemon['provider'] + '\n'
          + 'Version: ' + gatemon['version'] + '\n'
          + 'Zuletzt aktualisiert: ' + gatemon['lastupdated'] + '">'
          + gatemon['name']
          + '</td></tr>').appendTo($('#' + vpnserver_name + ' tbody'));

        // Iterate over services returned by gatemon
        counter = 0;
        var vpnserver_status = vpnserver_data['status'];
        for (var key in vpnserver_status) {
          counter++;
          if (gatemon_counter <= 1) {
            $('<td colspan="2" class="text-center">' + key + '</td>').appendTo($('#' + vpnserver_name + 'services'));
            $('<td class="text-center">IPv4</td>'
              + '<td class="text-center">IPv6</td>').appendTo($('#' + vpnserver_name + 'servicesfamily'));
          }

          $.each(['ipv4', 'ipv6'], function() {
            if (vpnserver_status[key][this]['up']) {
              $('<td class="good"' + (vpnserver_status[key][this]['time'] != undefined ? ' title="run time: ' + vpnserver_status[key][this]['time'] + 's"' : '') + '></td>').appendTo($('#' + vpnserver_name + gatemon['uuid']));
            } else {
              $('<td class="bad" title="' + (vpnserver_status[key][this]['time'] != undefined ? 'run time: ' + vpnserver_status[key][this]['time'] + 's' : '') + (vpnserver_status[key][this]['error-message'] != undefined ? "\nerror-message: " + vpnserver_status[key][this]['error-message'] : '') + '"></td>').appendTo($('#' + vpnserver_name + gatemon['uuid']));
            }
          });
        }

        if (gatemon_counter <= 1) {
          $('<th colspan="' + (2 * counter + 1) + '" class="text-center">' + vpnserver_data['name'] + '</th>').appendTo($('#' + vpnserver_name + 'server'));
        }
      });
    });
  }
});
