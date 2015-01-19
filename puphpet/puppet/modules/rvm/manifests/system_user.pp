define rvm::system_user () {
  include rvm::params

  ensure_resource('user', $name, {'ensure' => 'present' })
  include rvm::group

  $add_to_group = $osfamily ? {
    'Darwin' => "/usr/sbin/dseditgroup -o edit -a ${name} -t user ${rvm::params::group}",
    default  => "/usr/sbin/usermod -a -G ${rvm::params::group} ${name}",
  }
  $check_in_group = $osfamily ? {
    'Darwin' => "/usr/bin/dsmemberutil checkmembership -U ${name} -G ${rvm::params::group} | grep -q 'user is a member'",
    default  => "/bin/cat /etc/group | grep '^${rvm::params::group}:' | grep -qw ${name}",
  }
  exec { "rvm-system-user-${name}":
    command => $add_to_group,
    unless  => $check_in_group,
    require => [User[$name], Group[$rvm::params::group]];
  }
}
