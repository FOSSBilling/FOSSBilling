#!/bin/bash
# Install new locale
#
# $ sudo sh install_locale.sh fr_FR

cd /usr/share/locales
./install-language-pack $1
dpkg-reconfigure locales
locale -a