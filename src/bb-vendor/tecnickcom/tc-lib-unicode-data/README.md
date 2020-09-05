# tc-lib-unicode-data
*PHP library containing UTF-8 font definitions*

[![Latest Stable Version](https://poser.pugx.org/tecnickcom/tc-lib-unicode-data/version)](https://packagist.org/packages/tecnickcom/tc-lib-unicode-data)
[![Master Build Status](https://secure.travis-ci.org/tecnickcom/tc-lib-unicode-data.png?branch=master)](https://travis-ci.org/tecnickcom/tc-lib-unicode-data?branch=master)
[![Master Coverage Status](https://coveralls.io/repos/tecnickcom/tc-lib-unicode-data/badge.svg?branch=master&service=github)](https://coveralls.io/github/tecnickcom/tc-lib-unicode-data?branch=master)
[![License](https://poser.pugx.org/tecnickcom/tc-lib-unicode-data/license)](https://packagist.org/packages/tecnickcom/tc-lib-unicode-data)
[![Total Downloads](https://poser.pugx.org/tecnickcom/tc-lib-unicode-data/downloads)](https://packagist.org/packages/tecnickcom/tc-lib-unicode-data)

[![Develop Branch](https://img.shields.io/badge/-develop:-gray.svg)](https://github.com/tecnickcom/tc-lib-unicode-data/tree/develop)
[![Develop Build Status](https://secure.travis-ci.org/tecnickcom/tc-lib-unicode-data.png?branch=develop)](https://travis-ci.org/tecnickcom/tc-lib-unicode-data?branch=develop)
[![Develop Coverage Status](https://coveralls.io/repos/tecnickcom/tc-lib-unicode-data/badge.svg?branch=develop&service=github)](https://coveralls.io/github/tecnickcom/tc-lib-unicode-data?branch=develop)

[![Donate via PayPal](https://img.shields.io/badge/donate-paypal-87ceeb.svg)](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&currency_code=GBP&business=paypal@tecnick.com&item_name=donation%20for%20tc-lib-unicode-data%20project)
*Please consider supporting this project by making a donation via [PayPal](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&currency_code=GBP&business=paypal@tecnick.com&item_name=donation%20for%20tc-lib-unicode-data%20project)*

* **category**    Library
* **package**     \Com\Tecnick\Unicode\Data
* **author**      Nicola Asuni <info@tecnick.com>
* **copyright**   2011-2020 Nicola Asuni - Tecnick.com LTD
* **license**     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
* **link**        https://github.com/tecnickcom/tc-lib-unicode-data
* **SRC DOC**     https://tcpdf.org/docs/srcdoc/tc-lib-unicode-data
* **RPM**         https://bintray.com/tecnickcom/rpm/tc-lib-unicode-data
* **DEB**         https://bintray.com/tecnickcom/deb/tc-lib-unicode-data

## Description

PHP library containing UTF-8 font definitions.

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

Other make options allows you to install this library globally and build an RPM package.
Please check all the available options using `make help`.


## Example

Examples are located in the `example` directory.

Start a development server (requires PHP 5.4) using the command:

```
make server
```

and point your browser to <http://localhost:8000/index.php>


## Installation

Create a composer.json in your projects root-directory:

```json
{
    "require": {
        "tecnickcom/tc-lib-unicode-data": "dev-master"
    },
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:tecnickcom/tc-lib-unicode-data.git"
        }
    ]
}
```


## Packaging

This library is mainly intended to be used and included in other PHP projects using Composer.
However, since some production environments dictates the installation of any application as RPM or DEB packages,
this library includes make targets for building these packages (`make rpm` and `make deb`).
The packages are generated under the `target` directory.

When this library is installed using an RPM or DEB package, you can use it your code by including the autoloader:
```
require_once ('/usr/share/php/Com/Tecnick/Unicode/Data/autoload.php');
```

**NOTE:** Updated RPM and Debian packages of this library can be downloaded from: https://bintray.com/tecnickcom

## Developer(s) Contact

* Nicola Asuni <info@tecnick.com>
