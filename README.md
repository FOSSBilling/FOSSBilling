BoxBilling [![Download Latest](http://i.imgur.com/djy4ExU.png)](https://github.com/boxbilling/boxbilling/releases/latest) 
================================================================================
*If you want to download BoxBilling for use please click on `Download BoxBilling` button above instead of `git pull` command or GitHub's `Download ZIP` button!*

Open Source billing software

Requirements
================================================================================

<<<<<<< HEAD
* PHP 7.2
* Linux Operating System (64-bit) | Does not support Windows Operating Systems.
=======
* PHP 7.4.9 (cli) (built: Aug  7 2020 14:29:36) ( NTS )
Copyright (c) The PHP Group
Zend Engine v3.4.0, Copyright (c) Zend Technologies
    with Zend OPcache v7.4.9, Copyright (c), by Zend Technologies
* Ubuntu Codename:bionic (x86_64) Release:18.04| Does not support Windows Operating Systems.
>>>>>>> 4fc1b2175df4fe7935fe36c54c89be7a94e53dab
* PHP extensions:
  * openssl
  * curl
  * zlib
  * PDO
  * gettext
* MySQL or any PDO compatible SQL server

Recommended
================================================================================

<<<<<<< HEAD
* PHP 7.2
=======
* PHP 7.4.9
>>>>>>> 4fc1b2175df4fe7935fe36c54c89be7a94e53dab
* CentOS 64-bit Operating System
* PHP Extensions
  * openssl
  * curl
  * zlib
  * PDO
  * gettext
* MySQL or any PDO compatible SQL server

Getting started
================================================================================

Please read our installation instructions located at http://docs.boxbilling.com to get started
with BoxBilling

Contributing
================================================================================

1. Fork it
2. Create your feature branch (`git checkout -b my-new-feature`)
3. Commit your changes (`git commit -am 'Add some feature'`)
4. Push to the branch (`git push origin my-new-feature`)
5. Create new Pull Request to **develop** branch

Roadmap
================================================================================

* [ ] Support latest PHP version 7.3
* [ ] Add support for composer package manager

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

End User License Agreement & Other Restrictions
================================================================================
   Those that wish to distribute a modified version of BoxBilling must gain 
   permission from BoxBilling before releasing the software. All 
   authorised modified versions of BoxBilling must retain this copyright
   notice. All modified releases of BoxBilling must release the software under 
   the same license as the BoxBilling software (Apache License 2.0)
   
   Copyright © 2011-2018 BoxBilling. All rights reserved.
   www.boxbilling.com
