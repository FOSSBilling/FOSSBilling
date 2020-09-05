describe 'apache::mod::auth_kerb', :type => :class do
  let :pre_condition do
    'include apache'
  end
  context "on a Debian OS", :compile do
    let :facts do
      {
        :osfamily               => 'Debian',
        :operatingsystemrelease => '6',
        :concat_basedir         => '/dne',
      }
    end
    it { should contain_class("apache::params") }
    it { should contain_apache__mod("auth_kerb") }
    it { should contain_package("libapache2-mod-auth-kerb") }
  end
  context "on a RedHat OS", :compile do
    let :facts do
      {
        :osfamily               => 'RedHat',
        :operatingsystemrelease => '6',
        :concat_basedir         => '/dne',
      }
    end
    it { should contain_class("apache::params") }
    it { should contain_apache__mod("auth_kerb") }
    it { should contain_package("mod_auth_kerb") }
  end
  context "on a FreeBSD OS", :compile do
    let :facts do
      {
        :osfamily               => 'FreeBSD',
        :operatingsystemrelease => '9',
        :concat_basedir         => '/dne',
      }
    end
    it { should contain_class("apache::params") }
    it { should contain_apache__mod("auth_kerb") }
    it { should contain_package("www/mod_auth_kerb2") }
  end
end
