#!/bin/bash

# NPM vars
nodeModulesPath="node_modules"
packageLock="package-lock.json"
nodeBuildTimestamp="node_modules/package.stamp"

# Composer vars
composerVendorPath="src/vendor"
composerLock="composer.lock"
customVendorTimestamp="src/vendor/composer.stamp"

# Other vars
npmBuildNeeded=0
infoString="\033[1;33mINFO:\033[0m"
now=$(date +"%Y-%m-%d_%H:%M:%S")

echo -e "$infoString Automatically installing / updating dependencies and building the front-end. Please wait."
sleep 2

# If the NPM packages aren't installed or are outdated, install the locked versions
if [ ! -d "$nodeModulesPath" ] || [ ! -f "$nodeBuildTimestamp" ] || [ "$packageLock" -nt "$nodeBuildTimestamp" ]; then
    npm ci --include=dev
    npmBuildNeeded=1
elif [ ! -d "src/themes/admin_default/build" ]; then
    npmBuildNeeded=1
fi

# If the composer packages aren't installed or are outdated, install the locked versions
if [ ! -d "$composerVendorPath" ] || [ ! -f "$customVendorTimestamp" ] || [ "$composerLock" -nt "$customVendorTimestamp" ]; then
    composer install
    echo "$now" > "$customVendorTimestamp"
fi

# If we changed NPM packages, then re-run the build automatically
if [ $npmBuildNeeded -eq 1 ]; then
    npm run build
    echo "$now" > "$nodeBuildTimestamp"
fi

echo -e "$infoString Done!"
