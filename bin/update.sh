#!/bin/sh

UPDATE_URL="https://www.fossbilling.org/version/latest_update.zip"

wget -O update.zip -q $UPDATE_URL
unzip -o update.zip
php foss-update.php
rm -rf update.zip
rm -rf data/cache/*
