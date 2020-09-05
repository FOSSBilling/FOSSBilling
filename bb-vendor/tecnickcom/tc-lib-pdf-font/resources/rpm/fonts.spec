# SPEC file

%global c_vendor    %{_vendor}
%global gh_owner    %{_owner}
%global gh_project  %{_project}

Name:      %{_package}
Version:   %{_version}
Release:   %{_release}%{?dist}
Summary:   %{_fontdir} fonts data for tc-lib-pdf-font

Group:     Development/Libraries
License:   %{_license}
URL:       https://github.com/%{gh_owner}/%{gh_project}

BuildRoot: %{_tmppath}/%{name}-%{version}-%{release}-%(%{__id_u} -n)
BuildArch: noarch

Provides:  php-%{gh_project} = %{version}

%description
This package contains data extracted from the %{_fontdir} fonts for the tc-lib-pdf-font library.

%build
#(cd %{_current_directory} && make build)

%install
rm -rf $RPM_BUILD_ROOT
(cd %{_current_directory} && make install DESTDIR=$RPM_BUILD_ROOT PKGFONTDIR=%{_fontdir})

%clean
rm -rf $RPM_BUILD_ROOT
#(cd %{_current_directory} && make clean)

%files
%attr(-,root,root) %{_fontpath}
%attr(-,root,root) %{_docpath}
%docdir %{_docpath}

%changelog
* Tue Dec 01 2015 Nicola Asuni <info@tecnick.com> 1.0.0-1
- Initial Commit
