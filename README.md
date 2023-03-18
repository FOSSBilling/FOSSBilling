<h1 align="center">
  <br>
  <a href="https://fossbilling.org/">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/FOSSBilling/fossbilling.org/main/public/img/wordmark-white.png">
      <img alt="FOSSBilling logo" src="https://raw.githubusercontent.com/FOSSBilling/fossbilling.org/main/public/img/wordmark-black.png" height="100">
    </picture>
  </a>
  <br>
</h1>

<div align="center">

<a href="https://fossbilling.org/downloads/"><img src="https://raw.githubusercontent.com/FOSSBilling/fossbilling.org/main/public/img/gh-download-button.png" alt="Download button" width="400"/></a>

[![PHP Composer](https://github.com/FOSSBilling/FOSSBilling/actions/workflows/php.yml/badge.svg)](https://github.com/FOSSBilling/FOSSBilling/actions/workflows/php.yml)
[![Download Latest](https://img.shields.io/github/downloads/FOSSBilling/FOSSBilling/total)](https://github.com/FOSSBilling/FOSSBilling/releases/latest)
[![Stand With Ukraine](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/badges/StandWithUkraine.svg)](https://stand-with-ukraine.pp.ua)
[![Discord](https://img.shields.io/discord/747432407757488179?color=%237289FA&logo=discord&logoColor=%23FFF)](https://fossbilling.org/discord)
[![License](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)
[![Contributor Covenant](https://img.shields.io/badge/Contributor%20Covenant-2.1-4baaaa.svg)](CODE_OF_CONDUCT.md) 
[![CodeFactor](https://www.codefactor.io/repository/github/FOSSBilling/FOSSBilling/badge)](https://www.codefactor.io/repository/github/fossbilling/fossbilling)
[![Financial Contributors](https://opencollective.com/FOSSBilling/tiers/badge.svg?color=brightgreen)](https://opencollective.com/fossbilling)
[![Crowdin](https://badges.crowdin.net/e/c70c78b4ab1e71424ce53dcf6bca9b12/localized.svg)](https://fossbilling.crowdin.com/FOSSBilling)
</div>

> **Warning**
> FOSSBilling is under active development but is currently very much beta software, there may be stability or security issues and it is not recommended for use in active production environments yet!

**FOSSBilling** is a free open source, billing and client management solution. Whatever the size of your online services business, whether a startup or established, FOSSBilling can help you to automate your invoicing, incoming payments, and client management and communication.

If you run a web hosting business and are looking for an open-source alternative for billing and client management, then FOSSBilling is the answer. Although it is mostly used as a solution for hosting businesses, there is no reason why you can't use FOSSBilling for any other kind of online business, like digital downloads.

FOSSBilling is designed to be extensible and to integrate easily with your favorite server management software and payment gateways.

📥 This is self-hosted software that is free for anyone to install — All you need is a some basic knowledge, a web server, running PHP and a MySQL database. For more details, check the [requirements](#requirements) section.

## Contents

- [Contents](#contents)
- [Requirements](#requirements)
- [Example Configurations](#example-configurations)
- [Installation](#installation)
  - [Download the latest preview build](#download-the-latest-preview-build)
  - [Install from latest source code](#install-from-latest-source-code)
  - [Installing with Docker](#installing-with-docker)
- [Contributing](#contributing)
- [Star History](#star-history)
- [Licensing](#licensing)
- [Links](#links)

## Requirements

The following environment is highly recommended for running FOSSBilling. It *may* be possible to install and run the software in other environments, but it will be untested and unsupported.

- A suitable web server (Apache/nginx/LSWS/Lighttpd)
- PHP 8.0, 8.1 or 8.2 
- MySQL 8 (or higher), or MariaDB 10.3 (or higher) *Other direct MySQL compatible DBs should also work but are not supported.*
- The Following PHP extensions:
    - curl (optional, but recommended)
    - intl
    - mbstring (optional, but recommended)
    - openssl
    - pdo_mysql
    - xml
    - zlib

## Example Configurations
- [nginx](https://github.com/FOSSBilling/FOSSBilling/blob/master/data/nginx.conf)
- [Lighttpd](https://github.com/FOSSBilling/FOSSBilling/blob/master/data/lighttpd.conf)

## Installation
Installing FOSSBilling is pretty easy. Depending on how you plan to use it there are three different ways to install it:

1. If you are using shared hosting, or are installing FOSSBilling to use on a live production site (which is not currently recommended and comes with absolutely no guarantees), then you should probably download and install the **[latest preview build](#download-the-latest-preview-build)**.
3. If you're planning to contribute to FOSSBilling's development, and wanting to make pull requests in the future, please directly **[install from latest source code](#install-from-latest-source-code)** instead.
4. If you are familiar with Docker, you can also choose to install **[FOSSBilling in a Docker container](#installing-with-docker)**.

### Download the latest preview build
If you're planning to use FOSSBilling in a production environment (see the disclaimers above) then this will likely be the best option for you. They are not actual releases, but the preview builds are the most secure and stable versions currently available.

First, you should download the [latest preview build](https://fossbilling.org/downloads/preview). Unlike the source code, these preview builds already include the Composer packages, so you won't need to run Composer to install PHP packages. This is perfect if you are using shared hosting as you might not have the ability to run Composer yourself.

You can either download the .tar file to your local computer and then upload it to your server using FTP, or download it directly to your web server using wget or git clone. In either case, you will need to extract the contents into the public folder of your site (usually, that's called **"htdocs"** or **"public_html"**).

Your web directory's structure should now look like this:
- htdocs
    - data
    - library
    - modules
    - **...**

Next, you will also need to create a new empty MySQL database using the command line, or from your server control panel. Make a note of the database name, database user, and password, you will need them in the next step. 

Now, you have everything ready to start the installation of FOSSBilling, navigate to your domain using a web browser, and simply follow the on-screen instructions to complete the installation using the web installer. Ta-da, you've done it! 🎉

### Install from latest source code
To install the latest development version of FOSSBilling, you will need to get the [latest up-to-date ZIP archive](https://github.com/FOSSBilling/FOSSBilling/archive/master.zip) from the Github repository.

You can either download the .zip file to your local computer and then upload it to your server using FTP, or download it directly to your web server using wget or git clone. In either case, you will need to unzip it and make sure that the files contained in the archive are in the public folder of your site (usually, that's called **"htdocs"** or **"public_html"**).

Your web directory's structure should now look like this:
- htdocs
    - data
    - library
    - modules
    - **...**

Next, you will also need to create a new empty MySQL database using the command line, or from your server control panel. Make a note of the database name, database user, and password, you will need them later. 

We do not store the Composer packages in our GitHub repository, we use [Composer](https://getcomposer.org/) for that. Composer is a dependency manager for PHP, just like the NPM of Node.js, or PIP of Python.

If you don't have Composer installed, or it's your first time using it, you probably should read Composer's [getting started guide](https://getcomposer.org/doc/00-intro.md).

If you've already installed Composer, head over to the folder where you copied the content of the **"src"** folder, and run the following command to download the required packages to your web server:

```bash
$ composer install
```

Just as with Composer (see above) we do not store the final artifacts in our source repo. To build them make sure you have [Node.js](https://nodejs.org/en/download/) installed. 

Head over to your root directory and run

```bash
$ npm install
$ npm run build
```

Now, you have everything ready to start the installation of FOSSBilling,. Navigate to your domain using a web browser, and simply follow the on-screen instructions to complete the installation using the web installer. Ta-da, you've done it! 🎉

### Installing with Docker
<a href="https://www.docker.com/"><img align="right" src="https://www.docker.com/wp-content/uploads/2022/03/horizontal-logo-monochromatic-white.png" alt="Docker logo" width="125"></a>

We have developed an official Docker image, however, it should be noted that both the image and FOSSBilling are currently undergoing significant development and should not be considered production ready.

The image and install instructions can be found **[here](https://github.com/FOSSBilling/Docker)**.

## Contributing
🖥️ Welcome, fellow developer! 🙂

First of all, thank you for your interest, and for taking your time to contribute to FOSSBilling.

FOSSBilling is undergoing a revival and major code update. We are making steps forward day by day but there is still a lot of work to do, and we are happy to welcome new contributors. 

We have a set of guidelines for those wishing to contribute to FOSSBilling, and we encourage you to take a look at them here: **[contributors' guidelines](https://github.com/FOSSBilling/FOSSBilling/blob/master/CONTRIBUTING.md)**.

Your [pull requests](https://github.com/FOSSBilling/FOSSBilling/pulls) will be highly welcomed. If you're looking for something to start with, you can check the [open issues](https://github.com/FOSSBilling/FOSSBilling/issues) on our GitHub repository.

## Star History

[![Star History Chart](https://api.star-history.com/svg?repos=FOSSBilling/FOSSBilling&type=Date)](https://star-history.com/#FOSSBilling/FOSSBilling&Date)


**Got questions? Found a bug? Ideas for improvements?**

Don't hesitate to create an [issue](https://github.com/FOSSBilling/FOSSBilling/issues), or join us on [Discord](https://fossbilling.org/discord) to say hi.

⭐ Not a developer? Feel free to help by starring the repository. It helps us catch the attention of new developers who'd like to contribute. 

## Licensing

FOSSBilling is open source software and is released under the Apache v2.0 license. See [LICENSE](https://github.com/FOSSBilling/FOSSBilling/blob/master/LICENSE) for the full license terms.

This product includes the following third party work:
- GeoLite2 data created by MaxMind, available from [https://www.maxmind.com](https://www.maxmind.com).
- Open Source Iconography by [Pictogrammers](https://pictogrammers.com/) licensed under the [Pictogrammers Free License](https://pictogrammers.com/docs/general/license/).

## Links

* [Website](https://www.fossbilling.org/)
* [Documentation](https://fossbilling.org/docs)
* [Forum](https://forum.fossbilling.org)
* [Twitter](https://twitter.com/FOSSBilling)
* [Discord](https://fossbilling.org/discord)
