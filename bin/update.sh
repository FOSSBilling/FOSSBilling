#!/bin/sh

UPDATE_URL="http://www.boxbilling.org/version/latest_update.zip"

wget -O update.zip -q $UPDATE_URL
unzip -o update.zip
php update.php
rm -rf update.zip
rm -rf data/cache/*
