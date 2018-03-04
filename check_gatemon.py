#!/usr/bin/env python

#
# Nagios/Icinga check script for reporting problems detected by Gatemon.
#

import sys
import re
from collections import defaultdict
import argparse
import datetime
import dateutil.parser
import requests
import json
import traceback


verbose = True
def handleException(exc_type, exc_value, exc_traceback):
    """Handler for any uncaught exception."""
    if verbose:
        traceback.print_tb(exc_traceback)
    print "UNKNOWN (Exception %s: %s)" % (exc_type, exc_value)
    sys.exit(3)

if __name__ == "__main__":
    sys.excepthook = handleException

    parser = argparse.ArgumentParser()
    parser.add_argument("-u", "--url", required=True, help="URL of gatemon page")
    parser.add_argument("-s", "--server", help="server whose status shall be checked. Optionally, append \"=<label>\" to set server label.", action="append", default=[])
    parser.add_argument("-a", "--max-age", type=int, default=8*3600, help="max. age in seconds before a report will be ignored. Default: 28800 (8 hours)")
    parser.add_argument("-w", "--warning", type=int, default=70, help="warn if at least WARNING %% of the gatemons report a service as down. Default: 70%%")
    parser.add_argument("-c", "--critical", type=int, default=90, help="critical error if at least CRITICAL %% of the gatemons report a service as down. Default: 90%%")
    parser.add_argument("-i", "--ignore", help="RegExp pattern for service names that shall be ignored. Can be repeated.", action="append", default=[])
    parser.add_argument("-v", "--verbose", help="Enable verbose output.", action="store_true")
    args = parser.parse_args()

    nowTime = datetime.datetime.utcnow().replace(tzinfo=dateutil.tz.tzoffset('UTC', 0))
    cutoffTime = nowTime - datetime.timedelta(seconds=args.max_age)

    requestedServers = {}
    for serverString in args.server:
        if "=" in serverString:
            (hostName, serverLabel) = serverString.split("=", 1)
            requestedServers[hostName] = serverLabel
        else:
            requestedServers[serverString] = serverString

    if args.url.startswith("file:///"):
        mergedJson = json.load(open(args.url[len("file://"):]))
    else:
        r = requests.get("%s/data/merged.json" % args.url)
        mergedJson = r.json()

    # stores for each service the number of total/good/bad gatemon reports:
    services = defaultdict(lambda: {"total": 0, "good": 0, "bad": 0})
    # stores all server host names that were encountered in reports:
    knownHostNames = set()

    for monitor in mergedJson:
        lastUpdated = dateutil.parser.parse(monitor["lastupdated"])
        if lastUpdated < cutoffTime:
            continue

        for server in monitor["vpn-servers"]:
            knownHostNames.add(server["name"])
            if requestedServers and not(server["name"] in requestedServers):
                continue
            serverLabel = requestedServers.get(server["name"], server["name"])

            for serviceName in server:
                if serviceName not in ("ntp", "addresses", "dns", "uplink"):
                    continue
                for addrType in server[serviceName][0]:
                    fullName = "%s_%s_%s" % (serverLabel, serviceName, addrType)
                    success = server[serviceName][0][addrType]
                    services[fullName]["total"]+=1
                    if success:
                        services[fullName]["good"]+=1
                    else:
                        services[fullName]["bad"]+=1

    if requestedServers:
        for requestedServerName in requestedServers.keys():
            if requestedServerName not in knownHostNames:
                raise Exception("no data available for server \"%s\"" % requestedServerName)
    else:
        if not(knownHostNames):
            raise Exception("no data available at all")

    LEVEL_OK = 0
    LEVEL_WARNING = 1
    LEVEL_CRITICAL = 2

    reportedServices = []
    cumulatedLevel = 0
    resultTotals = {LEVEL_OK: 0, LEVEL_WARNING: 0, LEVEL_CRITICAL: 0}

    def addResult (level):
        global cumulatedLevel
        cumulatedLevel = max(cumulatedLevel, level)
        resultTotals[level] += 1

    def isIgnored (name):
        for regex in args.ignore:
            if re.search(regex, serviceName):
                return True
        return False

    for serviceName in sorted(services.keys()):
        if isIgnored(serviceName):
            continue
        result = services[serviceName]
        percentBad = (float(result["bad"]) / result["total"]) * 100.0
        perfDataLine = "%s=%d%%;%d;%d" % (serviceName, percentBad, args.warning, args.critical)
        readableLine = "%s: %d bad reports (%d%%)" % (serviceName, result["bad"], percentBad)
        reportedServices.append( (perfDataLine, readableLine) )
        if percentBad >= args.critical:
            addResult(LEVEL_CRITICAL)
        elif percentBad >= args.warning:
            addResult(LEVEL_WARNING)
        else:
            addResult(LEVEL_OK)

    resultText = "GATEMONS "
    if cumulatedLevel == LEVEL_OK:
        resultText += "OK"
    elif cumulatedLevel == LEVEL_WARNING:
        resultText += "WARNING"
    if cumulatedLevel == LEVEL_CRITICAL:
        resultText += "CRITICAL"
    resultText += " - %d criticals, %d warnings, %d services checked" % (resultTotals[LEVEL_CRITICAL], resultTotals[LEVEL_WARNING], len(reportedServices))

    for (perfDataLine, readableLine) in reportedServices:
        resultText += "\n%s" % readableLine

    resultText += "|" + (" ".join([ perfDataLine for (perfDataLine, readableLine) in reportedServices ])) + "\n"

    print resultText
    sys.exit(cumulatedLevel)
