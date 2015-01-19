class rvm::params() {

  $group = $::operatingsystem ? {
    default => 'rvm',
  }

  $proxy_url = undef
}
