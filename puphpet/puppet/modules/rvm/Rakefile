require 'rake/clean'
require 'puppet-lint/tasks/puppet-lint'

CLEAN.include('spec/fixtures/manifests/', 'spec/fixtures/modules/', 'doc', 'pkg')
CLOBBER.include('.tmp', '.librarian')

require 'puppetlabs_spec_helper/rake_tasks'
require 'puppet_blacksmith/rake_tasks'

PuppetLint.configuration.send("disable_80chars")

# use librarian-puppet to manage fixtures instead of .fixtures.yml
# offers more possibilities like explicit version management, forge
# downloads,...
task :librarian_spec_prep do
 sh "librarian-puppet install --path=spec/fixtures/modules/"
end
task :spec_prep => :librarian_spec_prep

require 'rspec/core/rake_task'
RSpec::Core::RakeTask.new(:beaker) do |c|
  c.pattern = "spec/acceptance/**/*_spec.rb"
end
task :beaker => :librarian_spec_prep

task :default => [:clean, :spec]
