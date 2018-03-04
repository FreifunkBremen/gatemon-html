#!/usr/bin/env python

#
# Nagios/Icinga check script for reporting problems detected by Gatemon.
#

import sys
import re
import argparse
import requests
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
    parser.add_argument("-s", "--server", help="server whose status shall be checked", action="append", default=[])
    #parser.add_argument("-a", "--max-age", type=int, help="max age in seconds before a report will be ignored")
    parser.add_argument("-w", "--warning", type=int, default=70, help="warn if at least WARNING %% of the gatemons report a service as down. Default: 70%%")
    parser.add_argument("-c", "--critical", type=int, default=90, help="critical error if at least CRITICAL %% of the gatemons report a service as down. Default: 90%%")
    parser.add_argument("-i", "--ignore", help="RegExp pattern for service names that shall be ignored. Can be repeated.", action="append", default=[])
    parser.add_argument("-v", "--verbose", help="Enable verbose output.", action="store_true")
    args = parser.parse_args()

    r = requests.get("%s/data/merged.json" % args.url)
    mergedJson = r.json()

#    import json
#    import os
#    mergedJson = json.load(open(os.path.dirname(os.path.realpath(__file__)) + "/merged.json"))
    #print mergedJson

    services = {}
    knownServerNames = set()
    for monitor in mergedJson:
        for server in monitor["vpn-servers"]:
            knownServerNames.add(server["name"])
            if args.server == [] or server["name"] in args.server:
                for serviceName in server:
                    if serviceName in ("ntp", "addresses", "dns", "uplink"):
                        for addrType in server[serviceName][0]:
                            fullName = "%s_%s_%s" % (server["name"], serviceName, addrType)
                            success = server[serviceName][0][addrType]
                            if not(fullName in services):
                                services[fullName] = {"total": 0, "good": 0, "bad": 0}
                            services[fullName]["total"]+=1
                            if success:
                                services[fullName]["good"]+=1
                            else:
                                services[fullName]["bad"]+=1

    if args.server:
        for requestedServer in args.server:
            if requestedServer not in knownServerNames:
                raise Exception("no data available for server \"%s\"" % requestedServer)

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
