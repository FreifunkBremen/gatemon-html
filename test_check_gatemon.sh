#!/bin/bash

#
# Some automated tests for check_gatemon.py
#
# Requires "faketime" tool to simulate a defined point in time.
#


# evaluates the passed parameters as a single command, and checks that the exit code is 0
assert ()
{
    "$@"
    result=$?
    expression="$*"
    if [ "$result" == 0 ]; then
        echo "GOOD: $(echo "$expression" | tr '\n' ' ' | cut -c 1-80)"
    else
        echo "FAIL: $expression"
    fi
}


# basic test
res=$(faketime '2018-02-25 22:20:00' ./check_gatemon.py -u file://`pwd`/merged.json -s vpn01.bremen.freifunk.net=vpn1 -i '_addresses_ipv6$')
assert [ "$?" -eq 0 ]
assert [ "$res" == 'GATEMONS OK - 0 criticals, 0 warnings, 7 services checked
vpn1_addresses_ipv4: 0 bad reports (0%)
vpn1_dns_ipv4: 0 bad reports (0%)
vpn1_dns_ipv6: 0 bad reports (0%)
vpn1_ntp_ipv4: 0 bad reports (0%)
vpn1_ntp_ipv6: 0 bad reports (0%)
vpn1_uplink_ipv4: 1 bad reports (25%)
vpn1_uplink_ipv6: 1 bad reports (25%)|vpn1_addresses_ipv4=0%;70;90 vpn1_dns_ipv4=0%;70;90 vpn1_dns_ipv6=0%;70;90 vpn1_ntp_ipv4=0%;70;90 vpn1_ntp_ipv6=0%;70;90 vpn1_uplink_ipv4=25%;70;90 vpn1_uplink_ipv6=25%;70;90' ]


# test with nonexisting hostname
res=$(faketime '2018-02-25 22:20:00' ./check_gatemon.py -u file://`pwd`/merged.json -s nonexisting-hostname 2>/dev/null)
assert [ "$?" -eq 3 ]
assert [ "$res" == "UNKNOWN (Exception <type 'exceptions.Exception'>: no data available for server \"nonexisting-hostname\")" ]


# test without any -s parameter
res=$(faketime '2018-02-25 22:20:00' ./check_gatemon.py -u file://`pwd`/merged.json -i '_addresses_' -i '_ntp_' -i '_uplink' -i '_ipv6$' 2>/dev/null)
assert [ "$?" -eq 2 ]
assert [ "$res" == 'GATEMONS CRITICAL - 2 criticals, 0 warnings, 6 services checked
vpn01.bremen.freifunk.net_dns_ipv4: 0 bad reports (0%)
vpn02.bremen.freifunk.net_dns_ipv4: 0 bad reports (0%)
vpn03.bremen.freifunk.net_dns_ipv4: 0 bad reports (0%)
vpn04.bremen.freifunk.net_dns_ipv4: 4 bad reports (100%)
vpn05.bremen.freifunk.net_dns_ipv4: 0 bad reports (0%)
vpn06.bremen.freifunk.net_dns_ipv4: 4 bad reports (100%)|vpn01.bremen.freifunk.net_dns_ipv4=0%;70;90 vpn02.bremen.freifunk.net_dns_ipv4=0%;70;90 vpn03.bremen.freifunk.net_dns_ipv4=0%;70;90 vpn04.bremen.freifunk.net_dns_ipv4=100%;70;90 vpn05.bremen.freifunk.net_dns_ipv4=0%;70;90 vpn06.bremen.freifunk.net_dns_ipv4=100%;70;90' ]


# test with different -w and -c parameters
res=$(faketime '2018-02-25 22:20:00' ./check_gatemon.py -u file://`pwd`/merged.json -s vpn01.bremen.freifunk.net=vpn1 -s vpn02.bremen.freifunk.net -i '_addresses_ipv6$' -w 24 -c 25 2>/dev/null)
assert [ "$?" -eq 2 ]
assert [ "$res" == 'GATEMONS CRITICAL - 2 criticals, 0 warnings, 14 services checked
vpn02.bremen.freifunk.net_addresses_ipv4: 0 bad reports (0%)
vpn02.bremen.freifunk.net_dns_ipv4: 0 bad reports (0%)
vpn02.bremen.freifunk.net_dns_ipv6: 0 bad reports (0%)
vpn02.bremen.freifunk.net_ntp_ipv4: 0 bad reports (0%)
vpn02.bremen.freifunk.net_ntp_ipv6: 0 bad reports (0%)
vpn02.bremen.freifunk.net_uplink_ipv4: 0 bad reports (0%)
vpn02.bremen.freifunk.net_uplink_ipv6: 0 bad reports (0%)
vpn1_addresses_ipv4: 0 bad reports (0%)
vpn1_dns_ipv4: 0 bad reports (0%)
vpn1_dns_ipv6: 0 bad reports (0%)
vpn1_ntp_ipv4: 0 bad reports (0%)
vpn1_ntp_ipv6: 0 bad reports (0%)
vpn1_uplink_ipv4: 1 bad reports (25%)
vpn1_uplink_ipv6: 1 bad reports (25%)|vpn02.bremen.freifunk.net_addresses_ipv4=0%;24;25 vpn02.bremen.freifunk.net_dns_ipv4=0%;24;25 vpn02.bremen.freifunk.net_dns_ipv6=0%;24;25 vpn02.bremen.freifunk.net_ntp_ipv4=0%;24;25 vpn02.bremen.freifunk.net_ntp_ipv6=0%;24;25 vpn02.bremen.freifunk.net_uplink_ipv4=0%;24;25 vpn02.bremen.freifunk.net_uplink_ipv6=0%;24;25 vpn1_addresses_ipv4=0%;24;25 vpn1_dns_ipv4=0%;24;25 vpn1_dns_ipv6=0%;24;25 vpn1_ntp_ipv4=0%;24;25 vpn1_ntp_ipv6=0%;24;25 vpn1_uplink_ipv4=25%;24;25 vpn1_uplink_ipv6=25%;24;25' ]


# test --max-age: only one gatemon report is less than 3 minutes old
res=$(faketime '2018-02-25 22:20:00' ./check_gatemon.py -u file://`pwd`/merged.json -s vpn01.bremen.freifunk.net --max-age 180 2>/dev/null)
assert [ "$?" -eq 2 ]
assert [ "$res" == 'GATEMONS CRITICAL - 1 criticals, 0 warnings, 8 services checked
vpn01.bremen.freifunk.net_addresses_ipv4: 0 bad reports (0%)
vpn01.bremen.freifunk.net_addresses_ipv6: 1 bad reports (100%)
vpn01.bremen.freifunk.net_dns_ipv4: 0 bad reports (0%)
vpn01.bremen.freifunk.net_dns_ipv6: 0 bad reports (0%)
vpn01.bremen.freifunk.net_ntp_ipv4: 0 bad reports (0%)
vpn01.bremen.freifunk.net_ntp_ipv6: 0 bad reports (0%)
vpn01.bremen.freifunk.net_uplink_ipv4: 0 bad reports (0%)
vpn01.bremen.freifunk.net_uplink_ipv6: 0 bad reports (0%)|vpn01.bremen.freifunk.net_addresses_ipv4=0%;70;90 vpn01.bremen.freifunk.net_addresses_ipv6=100%;70;90 vpn01.bremen.freifunk.net_dns_ipv4=0%;70;90 vpn01.bremen.freifunk.net_dns_ipv6=0%;70;90 vpn01.bremen.freifunk.net_ntp_ipv4=0%;70;90 vpn01.bremen.freifunk.net_ntp_ipv6=0%;70;90 vpn01.bremen.freifunk.net_uplink_ipv4=0%;70;90 vpn01.bremen.freifunk.net_uplink_ipv6=0%;70;90' ]
