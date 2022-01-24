#!/bin/bash

#
# Some automated tests for check_gatemons.py
#
# Requires "faketime" tool to simulate a defined point in time.
#


numTestsFailed=0

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
        numTestsFailed=$((numTestsFailed+1))
    fi
}


# basic test
res=$(faketime -f '2018-02-25 22:20:00' ./check_gatemons.py -u "file://$(pwd)/testdata.json" -s vpn01.bremen.freifunk.net=vpn1 -i '_addresses_ipv6$')
assert [ "$?" -eq 0 ]
assert [ "$res" == "GATEMONS OK - 0 criticals, 0 warnings, 7 services checked
vpn1_addresses_ipv4: 0 bad reports (0%)
vpn1_dns_ipv4: 0 bad reports (0%)
vpn1_dns_ipv6: 0 bad reports (0%)
vpn1_ntp_ipv4: 0 bad reports (0%)
vpn1_ntp_ipv6: 0 bad reports (0%)
vpn1_uplink_ipv4: 1 bad reports (25%)
vpn1_uplink_ipv6: 1 bad reports (25%)|'vpn1_addresses_ipv4'=0%;50;80 'vpn1_dns_ipv4'=0%;50;80 'vpn1_dns_ipv6'=0%;50;80 'vpn1_ntp_ipv4'=0%;50;80 'vpn1_ntp_ipv6'=0%;50;80 'vpn1_uplink_ipv4'=25%;50;80 'vpn1_uplink_ipv6'=25%;50;80 'gatemon_gatemon1'=107s;;28800 'gatemon_gatemon2'=403s;;28800 'gatemon_gatemon3'=1540s;;28800 'gatemon_gatemon4'=399s;;28800" ]


# test with nonexisting hostname
res=$(faketime -f '2018-02-25 22:20:00' ./check_gatemons.py -u "file://$(pwd)/testdata.json" -s nonexisting-hostname 2>/dev/null)
assert [ "$?" -eq 3 ]
assert [ "$res" == "UNKNOWN (Exception <class 'Exception'>: no data available for server \"nonexisting-hostname\")" ]


# test without any -s parameter
res=$(faketime -f '2018-02-25 22:20:00' ./check_gatemons.py -u "file://$(pwd)/testdata.json" -i '_addresses_' -i '_ntp_' -i '_uplink' -i '_ipv6$' 2>/dev/null)
assert [ "$?" -eq 2 ]
assert [ "$res" == "GATEMONS CRITICAL - 2 criticals, 0 warnings, 6 services checked
vpn01.bremen.freifunk.net_dns_ipv4: 0 bad reports (0%)
vpn02.bremen.freifunk.net_dns_ipv4: 0 bad reports (0%)
vpn03.bremen.freifunk.net_dns_ipv4: 0 bad reports (0%)
vpn04.bremen.freifunk.net_dns_ipv4: 4 bad reports (100%) (CRITICAL)
vpn05.bremen.freifunk.net_dns_ipv4: 0 bad reports (0%)
vpn06.bremen.freifunk.net_dns_ipv4: 4 bad reports (100%) (CRITICAL)|'vpn01.bremen.freifunk.net_dns_ipv4'=0%;50;80 'vpn02.bremen.freifunk.net_dns_ipv4'=0%;50;80 'vpn03.bremen.freifunk.net_dns_ipv4'=0%;50;80 'vpn04.bremen.freifunk.net_dns_ipv4'=100%;50;80 'vpn05.bremen.freifunk.net_dns_ipv4'=0%;50;80 'vpn06.bremen.freifunk.net_dns_ipv4'=100%;50;80 'gatemon_gatemon1'=107s;;28800 'gatemon_gatemon2'=403s;;28800 'gatemon_gatemon3'=1540s;;28800 'gatemon_gatemon4'=399s;;28800" ]


# test with different -w and -c parameters
res=$(faketime -f '2018-02-25 22:20:00' ./check_gatemons.py -u "file://$(pwd)/testdata.json" -s vpn01.bremen.freifunk.net=vpn1 -s vpn02.bremen.freifunk.net -i '_addresses_ipv6$' -w 24 -c 25 2>/dev/null)
assert [ "$?" -eq 2 ]
assert [ "$res" == "GATEMONS CRITICAL - 2 criticals, 0 warnings, 14 services checked
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
vpn1_uplink_ipv4: 1 bad reports (25%) (CRITICAL)
vpn1_uplink_ipv6: 1 bad reports (25%) (CRITICAL)|'vpn02.bremen.freifunk.net_addresses_ipv4'=0%;24;25 'vpn02.bremen.freifunk.net_dns_ipv4'=0%;24;25 'vpn02.bremen.freifunk.net_dns_ipv6'=0%;24;25 'vpn02.bremen.freifunk.net_ntp_ipv4'=0%;24;25 'vpn02.bremen.freifunk.net_ntp_ipv6'=0%;24;25 'vpn02.bremen.freifunk.net_uplink_ipv4'=0%;24;25 'vpn02.bremen.freifunk.net_uplink_ipv6'=0%;24;25 'vpn1_addresses_ipv4'=0%;24;25 'vpn1_dns_ipv4'=0%;24;25 'vpn1_dns_ipv6'=0%;24;25 'vpn1_ntp_ipv4'=0%;24;25 'vpn1_ntp_ipv6'=0%;24;25 'vpn1_uplink_ipv4'=25%;24;25 'vpn1_uplink_ipv6'=25%;24;25 'gatemon_gatemon1'=107s;;28800 'gatemon_gatemon2'=403s;;28800 'gatemon_gatemon3'=1540s;;28800 'gatemon_gatemon4'=399s;;28800" ]


# test with different -w and -c parameters
res=$(faketime -f '2018-02-25 22:20:00' ./check_gatemons.py -u "file://$(pwd)/testdata.json" -s vpn01.bremen.freifunk.net=vpn1 -s vpn02.bremen.freifunk.net -i '_addresses_ipv6$' -w 25 -c 26 2>/dev/null)
assert [ "$?" -eq 1 ]
assert [ "$res" == "GATEMONS WARNING - 0 criticals, 2 warnings, 14 services checked
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
vpn1_uplink_ipv4: 1 bad reports (25%) (WARNING)
vpn1_uplink_ipv6: 1 bad reports (25%) (WARNING)|'vpn02.bremen.freifunk.net_addresses_ipv4'=0%;25;26 'vpn02.bremen.freifunk.net_dns_ipv4'=0%;25;26 'vpn02.bremen.freifunk.net_dns_ipv6'=0%;25;26 'vpn02.bremen.freifunk.net_ntp_ipv4'=0%;25;26 'vpn02.bremen.freifunk.net_ntp_ipv6'=0%;25;26 'vpn02.bremen.freifunk.net_uplink_ipv4'=0%;25;26 'vpn02.bremen.freifunk.net_uplink_ipv6'=0%;25;26 'vpn1_addresses_ipv4'=0%;25;26 'vpn1_dns_ipv4'=0%;25;26 'vpn1_dns_ipv6'=0%;25;26 'vpn1_ntp_ipv4'=0%;25;26 'vpn1_ntp_ipv6'=0%;25;26 'vpn1_uplink_ipv4'=25%;25;26 'vpn1_uplink_ipv6'=25%;25;26 'gatemon_gatemon1'=107s;;28800 'gatemon_gatemon2'=403s;;28800 'gatemon_gatemon3'=1540s;;28800 'gatemon_gatemon4'=399s;;28800" ]


# test --max-age: only one gatemon report is less than 3 minutes old
res=$(faketime -f '2018-02-25 22:20:00' ./check_gatemons.py -u "file://$(pwd)/testdata.json" -s vpn01.bremen.freifunk.net --max-age 180 2>/dev/null)
assert [ "$?" -eq 2 ]
assert [ "$res" == "GATEMONS CRITICAL - 1 criticals, 0 warnings, 8 services checked
vpn01.bremen.freifunk.net_addresses_ipv4: 0 bad reports (0%)
vpn01.bremen.freifunk.net_addresses_ipv6: 1 bad reports (100%) (CRITICAL)
vpn01.bremen.freifunk.net_dns_ipv4: 0 bad reports (0%)
vpn01.bremen.freifunk.net_dns_ipv6: 0 bad reports (0%)
vpn01.bremen.freifunk.net_ntp_ipv4: 0 bad reports (0%)
vpn01.bremen.freifunk.net_ntp_ipv6: 0 bad reports (0%)
vpn01.bremen.freifunk.net_uplink_ipv4: 0 bad reports (0%)
vpn01.bremen.freifunk.net_uplink_ipv6: 0 bad reports (0%)|'vpn01.bremen.freifunk.net_addresses_ipv4'=0%;50;80 'vpn01.bremen.freifunk.net_addresses_ipv6'=100%;50;80 'vpn01.bremen.freifunk.net_dns_ipv4'=0%;50;80 'vpn01.bremen.freifunk.net_dns_ipv6'=0%;50;80 'vpn01.bremen.freifunk.net_ntp_ipv4'=0%;50;80 'vpn01.bremen.freifunk.net_ntp_ipv6'=0%;50;80 'vpn01.bremen.freifunk.net_uplink_ipv4'=0%;50;80 'vpn01.bremen.freifunk.net_uplink_ipv6'=0%;50;80 'gatemon_gatemon1'=107s;;180 'gatemon_gatemon2'=403s;;180 'gatemon_gatemon3'=1540s;;180 'gatemon_gatemon4'=399s;;180" ]


echo "$numTestsFailed tests failed"
if [ "$numTestsFailed" -eq 0 ]; then
    exit 0
else
    exit 1
fi
