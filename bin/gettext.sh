# Generate pot files

BASEDIR=$(dirname $0)
APPDIR=`cd $BASEDIR/.. ; pwd`
BB_CACHE_ADMIN="/tmp/bb_admin_cache/"
BB_CACHE_CLIENT="/tmp/bb_client_cache/"
BB_POT_ADMIN=$APPDIR/src/bb-locale/admin.pot
BB_POT_CLIENT=$APPDIR/src/bb-locale/messages.pot
BB_POT_EXCLUDE=$APPDIR/bin/gettext_exclude.po
BB_POT_INCLUDE=$APPDIR/bin/gettext_include.pot

rm -rf $BB_CACHE_ADMIN
rm -rf $BB_CACHE_CLIENT

php gettext.php

echo "Generating admin.pot file from" $BB_CACHE_ADMIN
cd $BB_CACHE_ADMIN
find . -iname "*.php" | xargs xgettext --omit-header --output=$BB_POT_ADMIN --from-code=UTF-8 --no-location --language=PHP

echo "Generating messages.pot file from" $BB_CACHE_CLIENT
cd $BB_CACHE_CLIENT
find . -iname "*.php" | xargs xgettext --omit-header --output=$BB_POT_CLIENT --from-code=UTF-8 --no-location --language=PHP

echo "Joining messages.pot file from" $APPDIR/src
cd $APPDIR/src
find . -iname "*.php" | xargs xgettext --omit-header --output=$BB_POT_CLIENT --join-existing --from-code=UTF-8 --no-location --language=PHP --keyword=__ --keyword=Box_Exception -x $BB_POT_EXCLUDE

echo "Joining gettext_include.pot file to messages.pot"
msgcat $BB_POT_INCLUDE $BB_POT_CLIENT --output=$BB_POT_CLIENT