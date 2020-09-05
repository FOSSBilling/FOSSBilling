# SPEC file

%global c_vendor    %{_vendor}
%global gh_owner    %{_owner}
%global gh_project  %{_project}

Name:      %{_package}
Version:   %{_version}
Release:   %{_release}%{?dist}
Summary:   PHP library to generate linear and bidimensional barcodes

Group:     Development/Libraries
License:   LGPLv3+
URL:       https://github.com/%{gh_owner}/%{gh_project}

BuildRoot: %{_tmppath}/%{name}-%{version}-%{release}-%(%{__id_u} -n)
BuildArch: noarch

Requires:  php(language) >= 5.4.0
Requires:  php-composer(%{c_vendor}/tc-lib-color) < 2.0.0
Requires:  php-composer(%{c_vendor}/tc-lib-color) >= 1.12.15
Requires:  php-bcmath
Requires:  php-date
Requires:  php-gd
Requires:  php-pcre

Provides:  php-composer(%{c_vendor}/%{gh_project}) = %{version}
Provides:  php-%{gh_project} = %{version}

%description
PHP classes to generate linear and bidimensional barcodes:
CODE 39, ANSI MH10.8M-1983, USD-3, 3 of 9, CODE 93, USS-93,
Standard 2 of 5, Interleaved 2 of 5, CODE 128 A/B/C,
2 and 5 Digits UPC-Based Extension, EAN 8, EAN 13, UPC-A,
UPC-E, MSI, POSTNET, PLANET, RMS4CC (Royal Mail 4-state Customer Code),
CBC (Customer Bar Code), KIX (Klant index - Customer index),
Intelligent Mail Barcode, Onecode, USPS-B-3200, CODABAR, CODE 11,
PHARMACODE, PHARMACODE TWO-TRACKS, Datamatrix ECC200, QR-Code, PDF417.

Optional dependency: php-pecl-imagick

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
* Tue Jul 02 2015 Nicola Asuni <info@tecnick.com> 1.2.0-1
- Changed package name, add provides section
* Tue Feb 24 2015 Nicola Asuni <info@tecnick.com> 1.0.0-1
- Initial Commit
