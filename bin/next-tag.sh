#!/bin/bash

#get highest tag number
VERSION=`git describe --abbrev=0 --tags 2>/dev/null`

if [ -z $VERSION ];then
    NEW_TAG="1.0.0"
fi

#replace . with space so can split into an array
VERSION_BITS=(${VERSION//./ })

#get number parts and increase last one by 1
VNUM1=${VERSION_BITS[0]}
VNUM2=${VERSION_BITS[1]}
VNUM3=${VERSION_BITS[2]}
VNUM3=$((VNUM3+1))

#create new tag
NEW_TAG="${VNUM1}.${VNUM2}.${VNUM3}"

echo $NEW_TAG

exit 0