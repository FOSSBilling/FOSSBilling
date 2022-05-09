#!/bin/bash

# BoxBilling installer
# This script installs BoxBilling using Docker Compose
#
# Copyright BoxBilling
# This source file is subject to the Apache-2.0 License that is bundled
# with this source code in the file LICENSE
#
# https://github.com/boxbilling/boxbilling/blob/master/LICENSE

# @TODO: Let the user change database name, username and password (or randomize the password if they want)
# @TODO: Detect permission errors and attempt to fix them automatically
# @TODO: Automatically download BoxBilling and let the script run even if the files don't already exist
# @TODO: SSL certificate generation
# @TODO: Automatically install Docker Engine and Compose
# @TODO: Handle start, down etc. commands too. This would make the script a lot more useful and possibly fill the need for a command-line interface. But it seems like a big task for now.
# @TODO: Maybe migrate from bash? I don't know if it's worth it for the installer but a full CLI might require us to go with something else. --evrifaessa
#
# @TODO: Don't run nginx on root. Let the user create an account and run nginx as that user
# ^ This might require us to dynamically change the ownership of the files, and also generate the Compose file on our own.
# ^ Also see https://docs.bitnami.com/tutorials/why-non-root-containers-are-important-for-security

INSTALLER_VERSION="v0.0.1"
BOXBILLING_VERSION="latest"

DOCKER_COMPOSE="docker compose"
DOCKER_WEB_CONTAINER_EXEC="$DOCKER_COMPOSE exec web"
DOCKER_PHP_CONTAINER_EXEC="$DOCKER_COMPOSE exec php"
DOCKER_DB_CONTAINER_EXEC="$DOCKER_COMPOSE exec mariadb"
DOCKER_PHP_EXECUTABLE_CMD="php -dmemory_limit=1G"

LOG_PATH="/var/log/boxbilling-installer.log"
PATH_PREFIX=$PWD

# Print errors
function print_error() {
    echo -e "\e[31m$1\e[0m"
}

# Print success
function print_success() {
    echo -e "\e[32m$1\e[0m"
}

# Print info
function print_info() {
    echo -e "\e[34m$1\e[0m"
}

# Print warning
function print_warning() {
    echo -e "\e[33m$1\e[0m"
}

# Print header
function print_header() {
    echo -e "\e[1m$1\e[0m"
}

# Print separator
function print_separator() {
    echo -e "\e[1m----------------------------------------------------\e[0m"
}

# Create necessary directories if they don't exist
function create_directories() {
    mkdir -p $PATH_PREFIX/src/bb-data/{cache,log,uploads}
}

# Change folder permissions
function change_folder_permissions() {
    print_info "Changing folder permissions..."
    chmod -R 777 $PATH_PREFIX/src/bb-data/{cache,log,uploads}
    chmod -R 777 $PATH_PREFIX/src/bb-themes
    print_success "Folder permissions changed."
}

# Start BoxBilling with a full rebuild
function rebuild_and_start() {
    print_header "Starting BoxBilling with a full rebuild..."
    $DOCKER_COMPOSE up -d --build --force-recreate --remove-orphans
    print_success "Successfully rebuilt."

    print_separator

    print_header "Waking up the container..."
    $DOCKER_COMPOSE up -d
    cp $PATH_PREFIX/src/bb-config-sample.php $PATH_PREFIX/src/bb-config.php
    print_separator
    
    print_header "Running the preparation script..."
	$DOCKER_PHP_CONTAINER_EXEC $DOCKER_PHP_EXECUTABLE_CMD ./bin/prepare.php
    print_separator
}

# Install Composer dependencies
function install_composer_dependencies() {
    print_header "Installing Composer dependencies..."
    $DOCKER_PHP_CONTAINER_EXEC composer install --working-dir=src --no-interaction --prefer-dist --no-dev
    print_success "Successfully installed the Composer dependencies."
    print_separator
}

# Remove installation leftovers
function remove_installation_leftovers() {
    print_header "Remove the leftover install folder now? [Y/n]"
    if [[ $REPLY =~ ^[Yy]$ ]]
    then
        print_header "Removing installation leftovers..."
        rm -rf $PATH_PREFIX/src/install
        print_success "Successfully removed \e[1m./src/install/\e[0m."
        print_separator  
    fi
}

print_header "BoxBilling installer $INSTALLER_VERSION"
print_separator
print_header "Welcome to the BoxBilling installer for Linux. This script will guide you through the installation of BoxBilling.
We will utilize Docker Compose in this script. If you want to install BoxBilling manually, please refer to the documentation."
print_separator
print_info "Working directory: $PWD"
print_separator

# Check if we're running on Linux
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    os="linux"
else
    print_error "This installer is only supported on Linux. Please refer to the README.md for installation instructions on other operating systems."
    exit
fi

# Abort if not root
if [[ "$EUID" -ne 0 ]]; then
    print_error "This script must be run with superuser privileges. Adding the sudo prefix or switching to the root user might help."
    exit
fi

# Check for the Docker Engine
if ! [ -x "$(command -v docker)" ]; then
    print_error "Docker Engine is not installed. Please install the Docker Engine and try again."
    print_info "For more information, see https://docs.docker.com/engine/install/. You'll likely want to install Docker Compose as well, so please opt-in to install that if the Docker installer asks you to do so."
    exit
fi

# In the future, we'll want to let the user choose what individual tasks to run.
if [ $# -eq 0 ]; then
    print_info "No arguments supplied. Continuing with the installation as default."
    print_separator
fi

# Check if the installation directory exists
if [ ! -d "$PATH_PREFIX/src/install" ]; then
    print_error "The installation folder must exist before installing BoxBilling... Download a new copy of BoxBilling and try again."
    exit
fi

# Check and delete the config file if it already exists
if [ -f "$PATH_PREFIX/src/bb-config.php" ]; then
    print_warning "The config file already exists. If BoxBilling is already installed and you ran the script as a mistake, answer no to the next prompt to quit safely."
    print_header "To continue, the installer will need to delete the existing configuration file first.\nKeep in mind that you will lose your existing configuration if you have an active installation."
    echo # new line
    
    read -p "Would you like to continue? [y/N] " -n 1 -r
    if [[ ! $REPLY =~ ^[Yy]$ ]]
    then
        [[ "$0" = "$BASH_SOURCE" ]] && exit 1 || return 1 # handle exits from shell or function but don't exit interactive shell
    fi
    
    echo # new line

    rm -f $PATH_PREFIX/src/bb-config.php
    print_success "The config file has been deleted."

    print_separator
fi

create_directories
change_folder_permissions
rebuild_and_start
install_composer_dependencies
remove_installation_leftovers

print_success "\e[1mInstallation complete.\e[0m"
print_header "To create your administrator account, please visit the following URL: http://localhost/bb-admin"
