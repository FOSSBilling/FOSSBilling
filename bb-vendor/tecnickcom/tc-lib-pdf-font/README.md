# tc-lib-pdf-font
*PHP library containing PDF font methods and utilities*

[![Latest Stable Version](https://poser.pugx.org/tecnickcom/tc-lib-pdf-font/version)](https://packagist.org/packages/tecnickcom/tc-lib-pdf-font)
[![Master Build Status](https://secure.travis-ci.org/tecnickcom/tc-lib-pdf-font.png?branch=master)](https://travis-ci.org/tecnickcom/tc-lib-pdf-font?branch=master)
[![Master Coverage Status](https://coveralls.io/repos/tecnickcom/tc-lib-pdf-font/badge.svg?branch=master&service=github)](https://coveralls.io/github/tecnickcom/tc-lib-pdf-font?branch=master)
[![License](https://poser.pugx.org/tecnickcom/tc-lib-pdf-font/license)](https://packagist.org/packages/tecnickcom/tc-lib-pdf-font)
[![Total Downloads](https://poser.pugx.org/tecnickcom/tc-lib-pdf-font/downloads)](https://packagist.org/packages/tecnickcom/tc-lib-pdf-font)

[![Develop Branch](https://img.shields.io/badge/-develop:-gray.svg)](https://github.com/tecnickcom/tc-lib-pdf-font/tree/develop)
[![Develop Build Status](https://secure.travis-ci.org/tecnickcom/tc-lib-pdf-font.png?branch=develop)](https://travis-ci.org/tecnickcom/tc-lib-pdf-font?branch=develop)
[![Develop Coverage Status](https://coveralls.io/repos/tecnickcom/tc-lib-pdf-font/badge.svg?branch=develop&service=github)](https://coveralls.io/github/tecnickcom/tc-lib-pdf-font?branch=develop)

[![Donate via PayPal](https://img.shields.io/badge/donate-paypal-87ceeb.svg)](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&currency_code=GBP&business=paypal@tecnick.com&item_name=donation%20for%20tc-lib-pdf-font%20project)
*Please consider supporting this project by making a donation via [PayPal](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&currency_code=GBP&business=paypal@tecnick.com&item_name=donation%20for%20tc-lib-pdf-font%20project)*

* **category**    Library
* **package**     \Com\Tecnick\Pdf\Font
* **author**      Nicola Asuni <info@tecnick.com>
* **copyright**   2011-2020 Nicola Asuni - Tecnick.com LTD
* **license**     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
* **link**        https://github.com/tecnickcom/tc-lib-pdf-font
* **SRC DOC**     https://tcpdf.org/docs/srcdoc/tc-lib-pdf-font
* **RPM**         https://bintray.com/tecnickcom/rpm/tc-lib-pdf-font
* **DEB**         https://bintray.com/tecnickcom/deb/tc-lib-pdf-font

## Description

PHP library containing PDF font methods and utilities.

The initial source code has been derived from [TCPDF](<http://www.tcpdf.org>).


## Getting started

First, you need to install all development dependencies using [Composer](https://getcomposer.org/):

```bash
$ curl -sS https://getcomposer.org/installer | php
$ mv composer.phar /usr/local/bin/composer
```

This project include a Makefile that allows you to test and build the project with simple commands.
To see all available options:

```bash
make help
```

To install all the development dependencies:

```bash
make build_dev
```

## Running all tests

Before committing the code, please check if it passes all tests using

```bash
make qa_all
```
this generates the phpunit coverage report in target/coverage.
Please check if the tests are covering all code.

Generate the documentation:

```bash
make docs
```

Generate static analysis reports in target/report:

```bash
make reports
```

To generate the default fonts you can use the command:
```bash
make fonts
```
The files are generated inside the `target/fonts` folder.
Please check the `util/convert.php` and `util/bulk_convert.php` to manually convert fonts.


Other make options allows you to install this library globally and build an RPM package.
Please check all the available options using `make help`.

## Installation

Create a composer.json in your projects root-directory:

```json
{
    "require": {
        "tecnickcom/tc-lib-pdf-font": "^1.7"
    }
}
```

Or add to an existing project with: 

```bash
composer require tecnickcom/tc-lib-pdf-font ^1.7
```

## Font conversion

To import fonts in bulk, please check the convert program in resources/cli.

## Packaging

This library is mainly intended to be used and included in other PHP projects using Composer.
However, since some production environments dictates the installation of any application as RPM or DEB packages,
this library includes make targets for building these packages (`make rpm` and `make deb`).
The packages are generated under the `target` directory.

When this library is installed using an RPM or DEB package, you can use it your code by including the autoloader:
```
require_once ('/usr/share/php/Com/Tecnick/Pdf/Font/autoload.php');
```

**NOTE:** Updated RPM and Debian packages of this library can be downloaded from: https://bintray.com/tecnickcom


## Developer(s) Contact

* Nicola Asuni <info@tecnick.com>
