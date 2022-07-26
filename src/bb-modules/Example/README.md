# Example module README file

This module provides a starting point for the developers on creating their FOSSBilling module.

Explore the files and comments in the code to understand the structure of the module better. See the social links on [our website](https://fossbilling.org) if you need further information.

In general, we use modules to extend the functionality of FOSSBilling.

All modules can communicate with the other modules using their API endpoints.

# Technical requirements about modules

## Required
* API folder has to contain a **manifest.json** file to describe itself. The module engine will look for this file to find information about your extension.

## Optional
* **README.md** - file for installation and getting started instructions
* **html_admin** folder - for admin area templates, to store custom *.html.twig files
* **html_client** folder - for client area templates, to store custom *.html.twig files
###### Controller folder
* **Admin.php** - if the module has install/uninstall instructions or
  an admin area interface
* **Client.php** - if the module has a client area interface
###### Api folder
* **Admin.php**         - Administrator API, only authorized administrators will be able to call these endpoints.
* **Client.php**        - Client API, only logged in clients will be able to call these endpoints.
* **Guest.php**         - Guest API, no authorization is needed for these endpoints. Don't provide confidential data over these endpoints. Anybody over the internet will be able to access these information, including bots.

# Tips
We recommend hosting your extensions on a public [GitHub](https://github.com) repository.
