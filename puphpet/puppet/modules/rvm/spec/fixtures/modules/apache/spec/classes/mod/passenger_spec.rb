describe 'apache::mod::passenger', :type => :class do
  let :pre_condition do
    'include apache'
  end
  context "on a Debian OS" do
    let :facts do
      {
        :osfamily               => 'Debian',
        :operatingsystemrelease => '6',
        :concat_basedir         => '/dne',
      }
    end
    it { should contain_class("apache::params") }
    it { should contain_apache__mod('passenger') }
    it { should contain_package("libapache2-mod-passenger") }
    it { should contain_file('passenger.load').with({
      'path' => '/etc/apache2/mods-available/passenger.load',
    }) }
    it { should contain_file('passenger_package.conf').with({
      'path' => '/etc/apache2/mods-available/passenger.conf',
    }) }
    it { should contain_file('passenger_package.conf').without_content }
    it { should contain_file('passenger_package.conf').without_source }
    it { should contain_file('passenger.conf').with({
      'path' => '/etc/apache2/mods-available/passenger_extra.conf',
    }) }
    it { should contain_file('passenger.conf').without_content(/PassengerRoot/) }
    it { should contain_file('passenger.conf').without_content(/PassengerRuby/) }
    describe "with passenger_root => '/usr/lib/example'" do
      let :params do
        { :passenger_root => '/usr/lib/example' }
      end
      it { should contain_file('passenger.conf').with_content(%r{PassengerRoot "/usr/lib/example"}) }
    end
    describe "with passenger_ruby => /usr/lib/example/ruby" do
      let :params do
        { :passenger_ruby => '/usr/lib/example/ruby' }
      end
      it { should contain_file('passenger.conf').with_content(%r{PassengerRuby "/usr/lib/example/ruby"}) }
    end
    describe "with passenger_high_performance => true" do
      let :params do
        { :passenger_high_performance => 'true' }
      end
      it { should contain_file('passenger.conf').with_content(/^  PassengerHighPerformance true$/) }
    end
    describe "with passenger_pool_idle_time => 1200" do
      let :params do
        { :passenger_pool_idle_time => 1200 }
      end
      it { should contain_file('passenger.conf').with_content(/^  PassengerPoolIdleTime 1200$/) }
    end
    describe "with passenger_max_requests => 20" do
      let :params do
        { :passenger_max_requests => 20 }
      end
      it { should contain_file('passenger.conf').with_content(/^  PassengerMaxRequests 20$/) }
    end
    describe "with passenger_stat_throttle_rate => 10" do
      let :params do
        { :passenger_stat_throttle_rate => 10 }
      end
      it { should contain_file('passenger.conf').with_content(/^  PassengerStatThrottleRate 10$/) }
    end
    describe "with passenger_max_pool_size => 16" do
      let :params do
        { :passenger_max_pool_size => 16 }
      end
      it { should contain_file('passenger.conf').with_content(/^  PassengerMaxPoolSize 16$/) }
    end
    describe "with rack_autodetect => true" do
      let :params do
        { :rack_autodetect => true }
      end
      it { should contain_file('passenger.conf').with_content(/^  RackAutoDetect true$/) }
    end
    describe "with rails_autodetect => true" do
      let :params do
        { :rails_autodetect => true }
      end
      it { should contain_file('passenger.conf').with_content(/^  RailsAutoDetect true$/) }
    end
    describe "with passenger_root => '/usr/lib/example'" do
      let :params do
        { :passenger_root => '/usr/lib/example' }
      end
      it { should contain_file('passenger.conf').with_content(/^  PassengerRoot "\/usr\/lib\/example"$/) }
    end
    describe "with passenger_ruby => /usr/lib/example/ruby" do
      let :params do
        { :passenger_ruby => '/usr/lib/example/ruby' }
      end
      it { should contain_file('passenger.conf').with_content(/^  PassengerRuby "\/usr\/lib\/example\/ruby"$/) }
    end
    describe "with passenger_use_global_queue => true" do
      let :params do
        { :passenger_use_global_queue => 'true' }
      end
      it { should contain_file('passenger.conf').with_content(/^  PassengerUseGlobalQueue true$/) }
    end
    describe "with mod_path => '/usr/lib/foo/mod_foo.so'" do
      let :params do
        { :mod_path => '/usr/lib/foo/mod_foo.so' }
      end
      it { should contain_file('passenger.load').with_content(/^LoadModule passenger_module \/usr\/lib\/foo\/mod_foo\.so$/) }
    end
    describe "with mod_lib_path => '/usr/lib/foo'" do
      let :params do
        { :mod_lib_path => '/usr/lib/foo' }
      end
      it { should contain_file('passenger.load').with_content(/^LoadModule passenger_module \/usr\/lib\/foo\/mod_passenger\.so$/) }
    end
    describe "with mod_lib => 'mod_foo.so'" do
      let :params do
        { :mod_lib => 'mod_foo.so' }
      end
      it { should contain_file('passenger.load').with_content(/^LoadModule passenger_module \/usr\/lib\/apache2\/modules\/mod_foo\.so$/) }
    end
    describe "with mod_id => 'mod_foo'" do
      let :params do
        { :mod_id => 'mod_foo' }
      end
      it { should contain_file('passenger.load').with_content(/^LoadModule mod_foo \/usr\/lib\/apache2\/modules\/mod_passenger\.so$/) }
    end

  end
  context "on a RedHat OS" do
    let :facts do
      {
        :osfamily               => 'RedHat',
        :operatingsystemrelease => '6',
        :concat_basedir         => '/dne',
      }
    end
    it { should contain_class("apache::params") }
    it { should contain_apache__mod('passenger') }
    it { should contain_package("mod_passenger") }
    it { should contain_file('passenger_package.conf').with({
      'path' => '/etc/httpd/conf.d/passenger.conf',
    }) }
    it { should contain_file('passenger_package.conf').without_content }
    it { should contain_file('passenger_package.conf').without_source }
    it { should contain_file('passenger.load').with({
      'path' => '/etc/httpd/conf.d/passenger.load',
    }) }
    it { should contain_file('passenger.conf').without_content(/PassengerRoot/) }
    it { should contain_file('passenger.conf').without_content(/PassengerRuby/) }
    describe "with passenger_root => '/usr/lib/example'" do
      let :params do
        { :passenger_root => '/usr/lib/example' }
      end
      it { should contain_file('passenger.conf').with_content(/^  PassengerRoot "\/usr\/lib\/example"$/) }
    end
    describe "with passenger_ruby => /usr/lib/example/ruby" do
      let :params do
        { :passenger_ruby => '/usr/lib/example/ruby' }
      end
      it { should contain_file('passenger.conf').with_content(/^  PassengerRuby "\/usr\/lib\/example\/ruby"$/) }
    end
  end
  context "on a FreeBSD OS" do
    let :facts do
      {
        :osfamily               => 'FreeBSD',
        :operatingsystemrelease => '9',
        :concat_basedir         => '/dne',
      }
    end
    it { should contain_class("apache::params") }
    it { should contain_apache__mod('passenger') }
    it { should contain_package("www/rubygem-passenger") }
  end
end
