import 'nodes/*.pp'

# Nice new git
  apt::ppa { 'ppa:git-core/ppa': }
  package { 'git' :
    ensure  => latest,
    require => Apt::Ppa['ppa:git-core/ppa']
  }

# Latest MySQL version ppa
  apt::ppa { 'ppa:ondrej/mysql-5.6': }
