# check_gatemons.py - Nagios/Icinga Script to Check Results of Gatemons

The `check_gatemons.py` script downloads a merged.json file from a gatemon-html web server, checks whether the Gatemons have reported the services as (sufficiently) alive, and report the result in a format that can be used by Nagios and Icinga.

## Requirements
- Python 2
- python-requests
- python-dateutil

## Usage
```
usage: check_gatemons.py [-h] -u URL [-s SERVER] [-a MAX_AGE] [-w WARNING]
                         [-c CRITICAL] [-i IGNORE] [-v]

optional arguments:
  -h, --help            show this help message and exit
  -u URL, --url URL     URL of gatemon page
  -s SERVER, --server SERVER
                        server whose status shall be checked. Optionally,
                        append "=<label>" to set server label.
  -a MAX_AGE, --max-age MAX_AGE
                        max. age in seconds before a report will be ignored.
                        Default: 28800 (8 hours)
  -w WARNING, --warning WARNING
                        warn if at least WARNING % of the gatemons report a
                        service as down. Default: 50%
  -c CRITICAL, --critical CRITICAL
                        critical error if at least CRITICAL % of the gatemons
                        report a service as down. Default: 80%
  -i IGNORE, --ignore IGNORE
                        RegExp pattern for service names that shall be
                        ignored. Can be repeated.
  -v, --verbose         Print verbose output to stderr.
```

## Tests
The `test_check_gatemons.sh` script runs some automatic tests. It needs the [faketime](https://github.com/wolfcw/libfaketime) tool to run.
