require 'spec_helper'
describe 'apache::mod::dev', :type => :class do
  [
    ['RedHat',  '6'],
    ['Debian',  '6'],
    ['FreeBSD', '9'],
  ].each do |osfamily, operatingsystemrelease|
    if osfamily == 'FreeBSD'
      let :pre_condition do
        'include apache::package'
      end
    end
    context "on a #{osfamily} OS" do
      let :facts do
        {
          :osfamily               => osfamily,
          :operatingsystemrelease => operatingsystemrelease,
        }
      end
      it { should contain_class('apache::dev') }
    end
  end
end
