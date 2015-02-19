BoxBilling [![Download Latest](http://i.imgur.com/djy4ExU.png)](https://github.com/boxbilling/boxbilling/releases/latest) 
================================================================================
*If you want to download BoxBilling for use please click on `Download BoxBilling` button above instead of `git pull` command or GitHub's `Download ZIP` button!*

[![Build Status](https://travis-ci.org/boxbilling/boxbilling.svg)](https://travis-ci.org/boxbilling/boxbilling)

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

Please read documentation at http://www.boxbilling.com/docs to get started
with BoxBilling

Contributing
================================================================================

1. Fork it
2. Create your feature branch (`git checkout -b my-new-feature`)
3. Commit your changes (`git commit -am 'Add some feature'`)
4. Push to the branch (`git push origin my-new-feature`)
5. Create new Pull Request

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

----
##### Virtual Machine Specifications #####

* OS     - Ubuntu 12.04
* PHP    - 5.5.4 
* Apache
* MySQL 5.6
* IP - 10.20.30.12
* servername - boxbilling.test
* target folder - /var/www/boxbilling

Support
================================================================================

* [Documentation](http://www.boxbilling.com/docs/)
* [Official website](http://www.boxbilling.com/)
* [@boxbilling](https://twitter.com/boxbilling)
* [Facebook](https://www.facebook.com/boxbilling)

Licensing
================================================================================

BoxBilling is licensed under the Apache License, Version 2.0. See LICENSE for full license text.
