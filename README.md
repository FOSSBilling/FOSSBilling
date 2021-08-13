<h1 align="center">
  <br>
  <a href="https://boxbilling.com/"><img src="https://raw.githubusercontent.com/boxbilling/boxbilling/master/src/bb-themes/boxbilling/assets/images/box.png" alt="BoxBilling" width="125"></a>
  <br>
  BoxBilling
  <br>
</h1>

<div align="center">
  
[![Build Status](https://travis-ci.com/boxbilling/boxbilling.svg?branch=master)](https://travis-ci.com/github/boxbilling/boxbilling)
[![Download Latest](https://img.shields.io/github/downloads/boxbilling/boxbilling/total)](https://github.com/boxbilling/boxbilling/releases/latest)
[![BoxBilling Issues](https://img.shields.io/github/issues/boxbilling/boxbilling.svg?style=popout)](https://github.com/boxbilling/boxbilling/issues)
[![BoxBilling Demo](https://img.shields.io/badge/boxbilling-demo-blue)](https://demo.boxbilling.com)
![BoxBilling Size](https://img.shields.io/github/repo-size/boxbilling/boxbilling.svg?style=popout)
[![License](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/boxbilling/boxbilling/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/boxbilling/boxbilling/?branch=master)
[![CodeFactor](https://www.codefactor.io/repository/github/boxbilling/boxbilling/badge)](https://www.codefactor.io/repository/github/boxbilling/boxbilling)

</div>

**BoxBilling** is a free open source, billing and client management solution. Whatever the size of your online services business, whether startup or established, BoxBilling can help you to automate your invoicing, incoming payments, and client management and communication.

If you run a web hosting business and are looking for an open source alternative for billing and client management, then BoxBilling is the answer. Although it is mostly used as a solution for hosting businesses, there is no reason why you can't use BoxBilling for any other kind of online business, like digital downloads. 

BoxBilling is designed to be extensible and to integrate easily with your favourite server management software and payment gateways.

📥 This is self-hosted software that is free for anyone to install — All you need is a web server, running PHP and a MySQL database. For more details, check the [requirements](#requirements) section.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
    - [Install the latest stable version](#download-the-latest-stable-version)
    - [Install from latest source code](#install-from-latest-source-code)
    - [Installing with Docker](#installing-with-docker)
- [Contributing](#contributing)
- [Licensing](#licensing)
- [Links](#links)

## Requirements

The following environment is highly recommended for running BoxBilling. It *may* be possible to install and run the software in other environments, but it will be untested and unsupported. 

- A suitable web server (Apache/Nginx/LSWS)
- PHP 7.2, or higher. *Note: PHP 8 is not yet supported.*
- MySQL 8, or higher. *MariaDB and other direct MySQL compatible DBs also work.*
- The Following PHP extensions:
    - pdo_mysql
    - curl
    - zlib
    - gettext
    - openssl

## Installation
Installing BoxBilling is pretty easy. Depending on how you plan to use it there are three different ways to install it:

1. If you are using shared hosting, or are installing BoxBilling to use on a live production site, then you should probably download and install the **[latest stable version](#download-the-latest-stable-version)**.
2. If you're planning to contribute to BoxBilling's development, and wanting to make pull requests in the future, please directly **[install from latest source code](#install-from-latest-source-code)** instead.
3. If you are familiar with Docker, can also choose to install **[BoxBilling in a Docker container](#installing-with-docker)**.

### Download the latest stable version
We make a new release of BoxBilling whenever we have some new cool stuff to introduce you to, or when we fix some bugs 🐞. If you're planning to use BoxBilling in a production environment then this will likely be the best option for you, as these releases should be more secure and stable.

First, you should [download the latest release](https://github.com/boxbilling/boxbilling/releases/latest) from our GitHub repository. Each release has a file called "BoxBilling.zip" attached to it, and that's exactly what you need to download. Unlike the source code itself, releases already include the Composer packages, so you won't need to run Composer to install PHP packages. This is perfect if you are using shared hosting as you might not have the ability to run Composer yourself.

You can either download the .zip file to your local computer and then upload it to your server using FTP, or download directly to your web server using wget or git clone. In either case you will need to unzip it and make sure that the files contained in the archive are in the public folder of your site (usually, that's called **"htdocs"** or **"public_html"**).

Your web directory's structure should now look like this:
- htdocs
    - bb-data
    - bb-library
    - bb-module
    - **...**

Next, you will also need to create a new empty MySQL database using the command line, or from your server control panel. Make a note of the database name, database user, and pasword, you will need them in the next step. 

Now, you have everything ready to start the installation of BoxBilling, navigate to your domain using a web browser, and simply follow the on-screen instructions to complete the installation using the web installer. Ta-da, you've done it! 🎉

### Install from latest source code
To install the latest development version of BoxBilling, you will need to get the [latest up-to-date ZIP archive](https://github.com/boxbilling/boxbilling/archive/master.zip) from the Github repository.

You can either download the .zip file to your local computer and then upload it to your server using FTP, or download directly to your web server using wget or git clone. In either case you will need to unzip it and make sure that the files contained in the archive are in the public folder of your site (usually, that's called **"htdocs"** or **"public_html"**).

Your web directory's structure should now look like this:
- htdocs
    - bb-data
    - bb-library
    - bb-module
    - **...**

Next, you will also need to create a new empty MySQL database using the command line, or from your server control panel. Make a note of the database name, database user, and pasword, you will need them later. 

We do not store the Composer packages in our GitHub repository, we use [Composer](https://getcomposer.org/) for that. Composer is a dependency manager for PHP, just like the NPM of Node.js, or PIP of Python.

If you don't have Composer installed, or it's your first time with Composer, you probably may want to read Composer's [getting started guide](https://getcomposer.org/doc/00-intro.md).

If you've already installed Composer, head over to the folder where you copied the content of the **"src"** folder to, and run the following command to download the required packages to your web server:

```bash
$ composer install
```

Now, you have everything ready to start the installation of BoxBilling, navigate to your domain using a web browser, and simply follow the on-screen instructions to complete the installation using the web installer. Ta-da, you've done it! 🎉

### Installing with Docker
<a href="https://www.docker.com/"><img align="right" src="https://www.docker.com/sites/default/files/d8/styles/role_icon/public/2019-07/horizontal-logo-monochromatic-white.png" alt="Docker logo" width="125"></a>

This guide assumes you already have [Docker](https://docs.docker.com/get-docker/), [Git](https://git-scm.com) and [GNU make](https://www.gnu.org/software/make/) installed.

To clone the repository, first run these commands in your command line:

```bash
# Clone this repository
$ git clone https://github.com/boxbilling/boxbilling

# Navigate to the local repository
$ cd boxbilling

# Run the app with some help from Docker
$ make all
```

Now, you can navigate to your web server in your browser. If you're using a PC, or directly a server without a server manager like Plesk, this address will probably be [localhost](http://localhost).

## Contributing
🖥️ Welcome, fellow developer! 🙂

First of all, thank you for your interest, and taking your time to contribute to BoxBilling.

BoxBilling is undergoing a revival and major code update. We are making steps forward day by day but there is still a lot of work to do, and we are happy to welcome new contributors. 

We have a set of guidelines for those wishing to contribute to BoxBilling, and we encourage you to take a look at them here: **[contributors' guidelines](https://github.com/boxbilling/boxbilling/blob/master/CONTRIBUTING.md)**.

Your [pull requests](https://github.com/boxbilling/boxbilling/pulls) will be highly welcomed. If you're looking for something to start with, you can check the [open issues](https://github.com/boxbilling/boxbilling/issues) on our GitHub repository.

**Got questions? Found a bug? Ideas for improvements?**

Don't hesitate to create an [issue](https://github.com/boxbilling/boxbilling/issues), or join us on [Slack](https://boxbilling.slack.com/) to say hi.

⭐ Not a developer? Feel free to help by starring the repository. It helps us catching attention of new developers who'd like to contribute. 

## Licensing

BoxBilling is open source software, and is released under the Apache v2.0 license. See [LICENSE](https://github.com/boxbilling/boxbilling/blob/master/LICENSE) for the full license terms.

## Links

* [Website](https://www.boxbilling.org/)
* [Documentation](https://docs.boxbilling.com/)
* [Slack](https://boxbilling.slack.com/)
