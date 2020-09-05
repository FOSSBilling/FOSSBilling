#!/bin/sh

URLBASE="http://www.boxbilling.com/version/latest.zip"

wget -O latest.zip $URLBASE
unzip -o latest.zip

cp bb-config-sample.php bb-config.php
chmod 755 bb-config.php
chmod 777 bb-uploads
chmod -R 777 bb-data

rm -rf latest.zip

