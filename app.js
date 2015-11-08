$(function() {
  $.ajax({
    url: 'http://status.ffhb.mortzu.de/status.json',
    success: parseResponse,
    dataType: 'json'
  });

  function parseResponse(data) {
    data['vpn-servers'].forEach(function(server) {
      var tr = $('<tr><td>' + server.name + '</td><td class="status-ipv4"></td><td class="status-ipv6"></td><td class="status-dns-ipv4"></td><td class="status-dns-ipv6"></td></tr>').appendTo($('#mytable tbody'));
      if (server.ipv4) {
        tr.find('td.status-ipv4').addClass('good');
      } else {
        tr.find('td.status-ipv4').addClass('bad');
      }

      if (server.ipv6) {
        tr.find('td.status-ipv6').addClass('good');
      } else {
        tr.find('td.status-ipv6').addClass('bad');
      }

      if (server.dns_ipv4) {
        tr.find('td.status-dns-ipv4').addClass('good');
      } else {
        tr.find('td.status-dns-ipv4').addClass('bad');
      }

      if (server.dns_ipv6) {
        tr.find('td.status-dns-ipv6').addClass('good');
      } else {
        tr.find('td.status-dns-ipv6').addClass('bad');
      }
    });
    $('#mytable tfoot td time').text(data.lastupdated);
  }
});
