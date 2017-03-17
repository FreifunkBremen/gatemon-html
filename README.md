# FFHB Gatemon HTML Frontend

This is the web frontend of the Bremen Freifunk Gateway Monitoring tool. It displays the status information that is collected by Gatemon monitoring clients.

Gatemon clients periodically check the status of FFHB gateway servers, and upload a report in JSON format to the web frontend, using the `put.php` page.

## Building
* download and install [Composer](https://getcomposer.org/)
* run `composer install`
* make sure the `token/` directory is not accessible for the web server (for Apache 2.4 the `token/.htaccess` file should do exactly that)

## Setup
For every Gatemon client create a secret token file in the `token/` directory: `touch token/$(pwgen 16 1)`. The file content doesn't matter; the file name is the secret token that must be configured in the Gatemon client.
