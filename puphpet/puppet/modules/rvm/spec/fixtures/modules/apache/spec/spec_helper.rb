require 'puppetlabs_spec_helper/module_spec_helper'

RSpec.configure do |c|
  c.treat_symbols_as_metadata_keys_with_true_values = true
end

shared_examples :compile, :compile => true do
  it { should compile.with_all_deps }
end
