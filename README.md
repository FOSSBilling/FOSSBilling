<h1 align="center">
  <br>
  <a href="https://boxbilling.com/"><img src="https://raw.githubusercontent.com/boxbilling/boxbilling/master/src/bb-themes/boxbilling/assets/images/box.png" alt="BoxBilling" width="125"></a>
  <br>
  BoxBilling
  <br>
</h1>

<div align="center">
  
[![Build Status](https://travis-ci.org/boxbilling/boxbilling.svg?branch=master)](https://travis-ci.org/boxbilling/boxbilling)
[![Download Latest](https://img.shields.io/github/downloads/boxbilling/boxbilling/total)](https://github.com/boxbilling/boxbilling/releases/latest)
[![BoxBilling Issues](https://img.shields.io/github/issues/boxbilling/boxbilling.svg?style=popout)](https://github.com/boxbilling/boxbilling/issues)
[![BoxBilling Demo](https://img.shields.io/badge/boxbilling-demo-blue)](https://demo.boxbilling.com)
![BoxBilling Size](https://img.shields.io/github/repo-size/boxbilling/boxbilling.svg?style=popout)
[![License](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/boxbilling/boxbilling/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/boxbilling/boxbilling/?branch=master)

</div>

**BoxBilling** is an open source, and free billing and client management software which helps any kind of companies, startups or even individuals who wish to automate their billing process along with tools and features which will allow you starting your business today!

You can simplify your web hosting business by putting your billing cycle in control by integrating your favorite server management software to BoxBilling.

BoxBilling is self-hosted — all you need is a [compatible](#requirements) web server, and a MySQL database. For further information, check the [requirements](#requirements) section.

## Table of content

- [Requirements](#requirements)
- [Installation](#installation)
    - [Obtaining a copy from GitHub releases](#obtaining-a-copy-from-github-releases)
    - [Running from the source code](#running-from-the-source-code)
    - [Running with Docker](#running-with-docker)
- [Contributing](#contributing)
- [Licensing](#licensing)
- [Links](#links)

## Requirements
Although BoxBilling *may* work in lower or smaller conditions, we **highly recommend** you to ensure that your environment has the following software installed:
- PHP 7.2, or higher.
- MySQL 8, or higher.
- Following PHP extensions:
    - pdo_mysql
    - curl
    - zlib
    - gettext
    - openssl

## Installation

### Obtaining a copy from GitHub releases

### Running from the source code
To clone and run BoxBilling, you'll first need to [download an up-to-date ZIP archive](https://github.com/boxbilling/boxbilling/archive/master.zip) and save it to your computer locally.

Then, extract the contents of the **"src"** folder inside the ZIP archive to your web server's public folder (usually, that's called **"htdocs"** or **"public_html"**). Your web directory's structure will now look like this:

- htdocs
    - bb-data
    - bb-library
    - bb-module
    - **...**

We do not store the Composer packages in our GitHub repository, we use [Composer](https://getcomposer.org/) for that. Composer is a dependency manager for PHP, just like the NPM of Node.js, or PIP of Python.

If you don't have Composer installed, or it's your first time with Composer, you probably may want to read Composer's [getting started guide](https://getcomposer.org/doc/00-intro.md).

If you don't have SSH access to your server, or your webmaster in your shared hosting environment doesn't let you use Composer, you may not be able to install the required packages for BoxBilling. In that case, you probably should install BoxBilling by [obtaining a ready-to-install copy from GitHub releases](#obtaining-a-copy-from-github-releases).

If you've already installed Composer, head over to the folder where you copied the content of the **"src"** folder to, and run the following command to download the required packages to your web server:

```bash
$ composer install
```

Now, as you have everything ready to start the installation of BoxBilling, head over to your web server, and follow on-screen instructions to complete the installation using web installer. Ta-da, you've done it! 🎉

### Running with Docker
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

Follow [contributors' guidelines](https://github.com/boxbilling/boxbilling/blob/master/CONTRIBUTING.md)

## Licensing

BoxBilling is licensed under the Apache License, Version 2.0. See LICENSE for full license text.

## Links

* [Website](https://www.boxbilling.com/)
* [Documentation](https://docs.boxbilling.com/)
* [Slack](https://boxbilling.slack.com/)
* [Twitter](https://twitter.com/boxbilling)
* [Facebook](https://www.facebook.com/boxbilling)
