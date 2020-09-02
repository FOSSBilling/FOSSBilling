# Example extension readme file

Module purpose is to provide a starting point for developer to get started
creating his own BoxBilling module.

Explore the files and comments in the code to better understand the structure
of module. Contact Development helpdesk at www.boxbilling.com if you need more
information.

In general modules are used to extend BoxBilling basic functionality.

All modules can access other modules via API or database

More about extensions at https://extensions.boxbilling.com/article/getting-started

# BoxBilling module requirements

## Required

* Folder must contain **manifest.json** file to describe itself

## Optional

* **README.md** - file for installation and getting started instructions
* Folder **html_admin**     - for admin area templates, to store custom *.phtml files
* Folder **html_client**    - for client area templates, to store custom *.phtml files
###### Controller folder
* **Admin.php** - if module has install/uninstall instructions or
  admin area interface
* **Client.php** - if module has client area interface
###### Api folder
* **Admin.php**         - file for Admin API
* **Client.php**        - file for Client API
* **Guest.php**         - file for Guest API

# Tips

We recommend to host your extensions on public [github.com](http://github.com) repository