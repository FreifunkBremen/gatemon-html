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
      + '<th>Version</th>'
      + '<th>Last Update</time></th>'
      + '</tr>'
      + '</thead>').appendTo($('#lastupdated'));

    // Iterate over gatemons
    data.forEach(function(gatemon) {
      // Increment counter
      gatemon_counter++;

      $('<tr>'
        + '<td>' + gatemon['name'] + '</td>'
        + '<td>' + gatemon['provider'] + '</td>'
        + '<td>' + gatemon['version'] + '</td>'
        + '<td><time class="timeago" datetime="' + gatemon['lastupdated'] + '">' + gatemon['lastupdated'] + '</time></td>'
        + '</tr>').appendTo($('#lastupdated'));
      $(".timeago").timeago();

      // Iterate over gatemon data
      gatemon['vpn-servers'].forEach(function(vpnserver_data) {
        // Short VPN hostname
        vpnserver_name = vpnserver_data['name'].split('.')[0];

        // Check if an element with ID of vpnserver exists
        if ($("#" + vpnserver_name).length == 0) {
          $('<div class="col-lg-6 col-md-12"><div class="well"><table class="table" id="' + vpnserver_name + '"><thead><tr id="' + vpnserver_name + 'server"></tr><tr id="' + vpnserver_name + 'services"><td></td></tr><tr id="' + vpnserver_name + 'servicesfamily"><td></td></tr></thead><tbody></tbody></table></div></div>').appendTo($('#content'));
        }

        $('<tr id="' + vpnserver_name + gatemon['uuid'] + '"><td title="Name: ' + gatemon['name'] + '\nProvider: ' + gatemon['provider'] + '\nVersion: ' + gatemon['version'] + '\nZuletzt aktualisiert: ' + gatemon['lastupdated'] + '">' + gatemon['name'] + '</td></tr>').appendTo($('#' + vpnserver_name + ' tbody'));

        // Iterate over services returned by gatemon
        counter = 0;
        for (var key in vpnserver_data) {
          // Check if item is an array
          if (Object.prototype.toString.call(vpnserver_data[key]) === '[object Array]') {
            counter++;
            if (gatemon_counter <= 1) {
              $('<td colspan="2" class="text-center">' + key + '</td>').appendTo($('#' + vpnserver_name + 'services'));
              $('<td class="text-center">IPv4</td><td class="text-center">IPv6</td>').appendTo($('#' + vpnserver_name + 'servicesfamily'));
            }

            $.each(['ipv4', 'ipv6'], function() {
              if (vpnserver_data[key][0][this]) {
                $('<td class="good"></td>').appendTo($('#' + vpnserver_name + gatemon['uuid']));
              } else {
                $('<td class="bad"></td>').appendTo($('#' + vpnserver_name + gatemon['uuid']));
              }
            });
          }
        }

        if (gatemon_counter <= 1) {
          $('<th colspan="' + (2 * counter + 1) + '" class="text-center">' + vpnserver_data['name'] + '</th>').appendTo($('#' + vpnserver_name + 'server'));
        }
      });
    });
  }
});
