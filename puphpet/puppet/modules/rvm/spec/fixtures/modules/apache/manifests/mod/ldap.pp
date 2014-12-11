class apache::mod::ldap {
  ::apache::mod { 'ldap': }
  # Template uses no variables
  file { 'ldap.conf':
    ensure  => file,
    path    => "${::apache::mod_dir}/ldap.conf",
    content => template('apache/mod/ldap.conf.erb'),
    require => Exec["mkdir ${::apache::mod_dir}"],
    before  => File[$::apache::mod_dir],
    notify  => Service['httpd'],
  }
}
