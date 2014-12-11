describe 'apache::mod::ssl', :type => :class do
  let :pre_condition do
    'include apache'
  end
  context 'on an unsupported OS' do
    let :facts do
      {
        :osfamily               => 'Magic',
        :operatingsystemrelease => '0',
        :concat_basedir         => '/dne',
      }
    end
    it { expect { subject }.to raise_error(Puppet::Error, /Unsupported osfamily:/) }
  end

  context 'on a RedHat OS' do
    let :facts do
      {
        :osfamily               => 'RedHat',
        :operatingsystemrelease => '6',
        :concat_basedir         => '/dne',
      }
    end
    it { should contain_class('apache::params') }
    it { should contain_apache__mod('ssl') }
    it { should contain_package('mod_ssl') }
  end

  context 'on a Debian OS' do
    let :facts do
      {
        :osfamily               => 'Debian',
        :operatingsystemrelease => '6',
        :concat_basedir         => '/dne',
      }
    end
    it { should contain_class('apache::params') }
    it { should contain_apache__mod('ssl') }
    it { should_not contain_package('libapache2-mod-ssl') }
  end

  context 'on a FreeBSD OS' do
    let :facts do
      {
        :osfamily               => 'FreeBSD',
        :operatingsystemrelease => '9',
        :concat_basedir         => '/dne',
      }
    end
    it { should contain_class('apache::params') }
    it { should contain_apache__mod('ssl') }
  end
end
