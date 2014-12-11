class apache::mod::dav_svn {
  Class['::apache::mod::dav'] -> Class['::apache::mod::dav_svn']
  include ::apache::mod::dav
  ::apache::mod { 'dav_svn': }
}
