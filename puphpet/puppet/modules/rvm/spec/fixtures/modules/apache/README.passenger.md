# Passenger

Just enabling the Passenger module is insufficient for the use of Passenger in production. Passenger should be tunable to better fit the environment in which it is run while being aware of the resources it required.

To this end the Apache passenger module has been modified to apply system wide Passenger tuning declarations to `passenger.conf`. Declarations specific to a virtual host should be passed through when defining a `vhost` (e.g. `rack_base_uris` parameter on the `apache::vhost` class, check `README.md`).

Also, general apache module loading parameters can be supplied to enable using a customized passenger module in place of a default-package-based version of the module.

# Parameters for `apache::mod::passenger`

The following declarations are supported and can be passed to `apache::mod::passenger` as parameters, for example:

```
class {'apache::mod::passenger':
  passenger_high_performance  => 'on',
  rails_autodetect            => 'off',
  mod_lib_path                => '/usr/lib/apache2/custom_modules',
}
```

The general form is using the all lower case version of the declaration.

## Parameters used with passenger.conf

If you pass a default value to `apache::mod::passenger` it will be ignored and not passed through to the configuration file. 

### passenger_high_performance

Default is `off`, when turned `on` Passenger runs in a higher performance mode that can be less compatible with other Apache modules.

http://www.modrails.com/documentation/Users%20guide%20Apache.html#PassengerHighPerformance

### passenger_max_pool_size

Set's the maximum number of Passenger application processes that may simultaneously run. The default value is 6.

http://www.modrails.com/documentation/Users%20guide%20Apache.html#_passengermaxpoolsize_lt_integer_gt

### passenger_pool_idle_time

The maximum number of seconds a Passenger Application process will be allowed to remain idle before being shut down. The default value is 300.

http://www.modrails.com/documentation/Users%20guide%20Apache.html#PassengerPoolIdleTime

### passenger_max_requests

The maximum number of request a Passenger application will process before being restarted. The default value is 0, which indicates that a process will only shut down if the Pool Idle Time (see above) expires.

http://www.modrails.com/documentation/Users%20guide%20Apache.html#PassengerMaxRequests

### passenger_stat_throttle_rate

Sets how often Passenger performs file system checks, at most once every _x_ seconds. Default is 0, which means the checks are performed with every request.

http://www.modrails.com/documentation/Users%20guide%20Apache.html#_passengerstatthrottlerate_lt_integer_gt

### rack_autodetect

Should Passenger automatically detect if the document root of a virtual host is a Rack application. The default is `on`

http://www.modrails.com/documentation/Users%20guide%20Apache.html#_rackautodetect_lt_on_off_gt

### rails_autodetect

Should Passenger automatically detect if the document root of a virtual host is a Rails application. The default is on.

http://www.modrails.com/documentation/Users%20guide%20Apache.html#_railsautodetect_lt_on_off_gt

### passenger_use_global_queue

Allows toggling of PassengerUseGlobalQueue.  NOTE: PassengerUseGlobalQueue is the default in Passenger 4.x and the versions >= 4.x have disabled this configuration option altogether.  Use with caution.

## Parameters used to load the module

Unlike the tuning parameters specified above, the following parameters are only used when loading customized passenger modules.

### mod_package

Allows overriding the default package name used for the passenger module package.

### mod_package_ensure

Allows overriding the package installation setting used by puppet when installing the passenger module. The default is 'present'.

### mod_id

Allows overriding the value used by apache to identify the passenger module. The default is 'passenger_module'.

### mod_lib_path

Allows overriding the directory path used by apache when loading the passenger module. The default is the value of `$apache::params::lib_path`.

### mod_lib

Allows overriding the library file name used by apache when loading the passenger module. The default is 'mod_passenger.so'.

### mod_path

Allows overriding the full path to the library file used by apache when loading the passenger module. The default is the concatenation of the `mod_lib_path` and `mod_lib` parameters.

# Dependencies

RedHat-based systems will need to configure additional package repositories in order to install Passenger, specifically:

* [Extra Packages for Enterprise Linux](https://fedoraproject.org/wiki/EPEL)
* [Phusion Passenger](http://passenger.stealthymonkeys.com)

Configuration of these repositories is beyond the scope of this module and is left to the user.

# Attribution

The Passenger tuning parameters for the `apache::mod::puppet` Puppet class was modified by Aaron Hicks (hicksa@landcareresearch.co.nz) for work on the NeSI Project and the Tuakiri New Zealand Access Federation as a fork from the PuppetLabs Apache module on GitHub.

* https://github.com/puppetlabs/puppetlabs-apache
* https://github.com/nesi/puppetlabs-apache
* http://www.nesi.org.nz//
* https://tuakiri.ac.nz/confluence/display/Tuakiri/Home

# Copyright and License

Copyright (C) 2012 [Puppet Labs](https://www.puppetlabs.com/) Inc

Puppet Labs can be contacted at: info@puppetlabs.com

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

  http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
