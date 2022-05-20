#!/bin/sh

URLBASE="http://www.boxbilling.org/version/latest.zip"

wget -O latest.zip $URLBASE
unzip -o latest.zip

cp config-sample.php config.php
chmod 755 config.php
chmod 777 uploads
chmod -R 777 data

rm -rf latest.zip

