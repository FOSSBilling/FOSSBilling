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

**BoxBilling** is an open source billing and client management software which helps any kind of companies, startups or even individuals who wish to automate their billing process along with tools and features which will allow you starting your business today!

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
* PHP 7.2, or higher.
* MySQL 8, or higher.

## Installation

### Obtaining a copy from GitHub releases

### Running from the source code
To clone and run BozBilling, you'll first need to [download an up-to-date ZIP archive](https://github.com/boxbilling/boxbilling/archive/master.zip) and save it to your computer locally.

Then, 

```bash
# Install Composer packages
$ composer install
```

### Running with Docker

Assuming you already have [Docker](https://docs.docker.com/get-docker/) and [GNU make](https://www.gnu.org/software/make/) installed,

```console
make all
```
Now, you can navigate to [localhost](http://localhost/).

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
