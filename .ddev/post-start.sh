#!/bin/bash

# NPM vars
nodeModulesPath="node_modules"
packageLock="package.lock"
nodeBuildLock="node_modules/package.lock"

# Composer vars
composerVendorPath="src/vendor"
composerLock="composer.lock"
customVendorLock="src/vendor/composer.lock" # Does this file for sure change each time the deps are updated?

# Other vars
npmBuildNeeded=0
infoString="\033[1;33mINFO:\033[0m"
now=$(date +"%Y-%m-%d_%H:%M:%S")

echo -e "$infoString Automatically installing / updating dependencies and building the front-end. Please wait."
sleep 2

# If the NPM packages aren't installed or are outdated, install the locked versions
if [ ! -d "$nodeModulesPath" ] || [ ! -f "$nodeBuildLock" ] || [ "$packageLock" -nt "$nodeBuildLock" ]; then
    npm install
    npmBuildNeeded=1
elif [ ! -d "src/themes/admin_default/build" ]; then
    npmBuildNeeded=1
fi

# If the composer packages aren't installed or are outdated, install the locked versions
if [ ! -d "$composerVendorPath" ] || [ ! -f "$customVendorLock" ] || [ "$composerLock" -nt "$customVendorLock" ]; then
    echo "$now" > $customVendorLock
    composer install
fi

# If we changed NPM packages, then re-run the build automatically
if [ $npmBuildNeeded -eq 1 ]; then
    npm run build
    echo "$now" > $nodeBuildLock
fi

echo -e "$infoString Done!"
