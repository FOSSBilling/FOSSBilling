import 'nodes/*.pp'

# Nice new git
  apt::ppa { 'ppa:git-core/ppa': }
  package { 'git' :
    ensure  => latest,
    require => Apt::Ppa['ppa:git-core/ppa']
  }
