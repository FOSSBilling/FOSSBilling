#
class mysql::server::install {

  package { 'mysql-server':
    ensure  => latest,
    name   => $mysql::server::package_name,
    require => Apt::Ppa['ppa:ondrej/mysql-5.6']
  }

}
