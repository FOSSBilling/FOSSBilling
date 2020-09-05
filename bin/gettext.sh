#!/bin/sh
# Generate pot file

BASEDIR=$(dirname $0)
APPDIR=`cd $BASEDIR/.. ; pwd`
BB_CACHE="/tmp/bb-translations/"
BB_POT=$APPDIR/src/bb-locale/messages.pot
BB_POT_EXCLUDE=$APPDIR/bin/gettext_exclude.po
BB_POT_INCLUDE=$APPDIR/bin/gettext_include.pot

rm -rf $BB_CACHE

php gettext.php

echo "Generating messages.pot from template files"
cd $BB_CACHE
find . -iname "*.php" | xargs xgettext --omit-header --output=$BB_POT --join-existing --from-code=UTF-8 --no-location --language=PHP --keyword=gettext -x $BB_POT_EXCLUDE

echo "Generating messages.pot from php files"
cd $APPDIR/src
find . -iname '*.php' -not -path './bb-vendor/*' | xargs xgettext --omit-header --output=$BB_POT --join-existing --from-code=UTF-8 --no-location --language=PHP --keyword=__ --keyword=Box_Exception --keyword=Payment_Exception --keyword=gettext -x $BB_POT_EXCLUDE
echo "Joining gettext_include.pot file to messages.pot"
msgcat $BB_POT $BB_POT_INCLUDE  --output=$BB_POT

cd $APPDIR/bin
php gettext_header.php