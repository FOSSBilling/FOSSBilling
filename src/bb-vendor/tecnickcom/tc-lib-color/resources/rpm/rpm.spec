# SPEC file

%global c_vendor    %{_vendor}
%global gh_owner    %{_owner}
%global gh_project  %{_project}

Name:      %{_package}
Version:   %{_version}
Release:   %{_release}%{?dist}
Summary:   PHP library to manipulate various color representations

Group:     Development/Libraries
License:   LGPLv3+
URL:       https://github.com/%{gh_owner}/%{gh_project}

BuildRoot: %{_tmppath}/%{name}-%{version}-%{release}-%(%{__id_u} -n)
BuildArch: noarch

Requires:  php(language) >= 5.3.0
Requires:  php-pcre

Provides:  php-composer(%{c_vendor}/%{gh_project}) = %{version}
Provides:  php-%{gh_project} = %{version}

%description
PHP library to manipulate various color representations (GRAY, RGB, HSL, CMYK) and parse Web colors.

%build
(cd %{_current_directory} && make build)

%install
rm -rf $RPM_BUILD_ROOT
(cd %{_current_directory} && make install DESTDIR=$RPM_BUILD_ROOT)

%clean
rm -rf $RPM_BUILD_ROOT
(cd %{_current_directory} && make clean)

%files
%attr(-,root,root) %{_libpath}
%attr(-,root,root) %{_docpath}
%docdir %{_docpath}
#%config(noreplace) %{_configpath}*

%changelog
* Tue Jul 02 2015 Nicola Asuni <info@tecnick.com> 1.5.0-1
- Changed package name, add provides section
* Tue Feb 24 2015 Nicola Asuni <info@tecnick.com> 1.0.0-1
- Initial Commit
