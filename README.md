BoxBilling [![Download Latest](http://i.imgur.com/djy4ExU.png)](https://github.com/boxbilling/boxbilling/releases/latest) 
================================================================================
*If you want to download BoxBilling for use please click on `Download BoxBilling` button above instead of `git pull` command or GitHub's `Download ZIP` button!*

[![Build Status](https://travis-ci.org/boxbilling/boxbilling.svg?branch=master)](https://travis-ci.org/boxbilling/boxbilling)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/boxbilling/boxbilling/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/boxbilling/boxbilling/?branch=master)

Open Source billing software

Requirements
================================================================================

* PHP >=5.3.3
* PHP extensions:
  * mcrypt
  * curl
  * zlib
  * PDO
  * gettext
* MySQL or any PDO compatible SQL server

Getting started
================================================================================

Please read documentation at http://docs.boxbilling.com to get started
with BoxBilling

Contributing
================================================================================

1. Fork it
2. Create your feature branch (`git checkout -b my-new-feature`)
3. Commit your changes (`git commit -am 'Add some feature'`)
4. Push to the branch (`git push origin my-new-feature`)
5. Create new Pull Request to **develop** branch

Using Vagrant
================================================================================
Vagrant is [very well documented](https://docs.vagrantup.com/v2/) but here are a few common commands:

* `vagrant up` starts the virtual machine and provisions it
* `vagrant suspend` will essentially put the machine to 'sleep' with `vagrant resume` waking it back up
* `vagrant halt` attempts a graceful shutdown of the machine and will need to be brought back with `vagrant up`
* `vagrant ssh` gives you shell access to the virtual machine

Install vagrant-hostmanager plugin
    
    $ vagrant plugin install vagrant-hostmanager
    
to update /etc/hosts file 

Using Grunt
===========
To create minified js and css files for theme admin_default run:
`./node_modules/.bin/grunt` from project root directory

If you want to use not minified versions of admin_default theme:
* [separate JS files in layout](https://github.com/boxbilling/boxbilling/blob/5e19912e7287b76e6b760899a7f9d2a4f3c1125c/src/bb-themes/admin_default/html/layout_default.phtml#L17-L24)
* [separate CSS](https://github.com/boxbilling/boxbilling/blob/2636cae130a94cdd827fb5f4acf46b0cdfebbb30/src/bb-themes/admin_default/html/partial_styles.phtml)

----
##### Virtual Machine Specifications #####

* OS     - Ubuntu 12.04
* PHP    - 5.4.* 
* Apache
* MySQL 5.6
* IP - 10.20.30.12
* servername - boxbilling.test
* target folder - /var/www/boxbilling

Support
================================================================================

* [Documentation](http://docs.boxbilling.com/)
* [Official website](http://www.boxbilling.com/)
* [@boxbilling](https://twitter.com/boxbilling)
* [Facebook](https://www.facebook.com/boxbilling)

Licensing
================================================================================

BoxBilling is licensed under the Apache License, Version 2.0. See LICENSE for full license text.
