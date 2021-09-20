INTRODUCTION
------------

Verify User Email before creating an Account on Drupal. This is to avoid a Spam 
Account created using a non-existing email address. This Module makes sure that 
the email address used in account creation has a valid inbox.

INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module. Visit
   https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules
   for further information.

REQUIREMENTS
------------

No special requirements.

INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module.
   See: https://www.drupal.org/node/895232 for further information.
   
   
CONFIGURATION
-------------

* Visit `admin/config/people/userverify` (Configuration -> People -> New User 
Email Verification)
* `Random key to generate verify email hash` : Put Random characters upto 32.
* `User Verification Email Template` : Prepare you custom template and user 
two tokens to provide link.
* `User Verification Help text` : Provide Help Text on Verification form.

--- 

* After this configuration is saved and when a new user visits the 
registration tab.
* Registration form auto redirect to another form to verify your email.
* Once a user provides the email address, it sends a verification link.
* Once the user clicks on that link it redirects to the actual form and here 
email address is auto filled and field marked as readonly.

MAINTAINERS
-----------

Current maintainers:
 * Sunil Pawar - https://www.drupal.org/u/sunilpawar

This project has been sponsored by:
 * Skvare - https://www.drupal.org/skvare
   Skvare (pronounced "square") is an established team of Drupal and CiviCRM 
   experts that specialize in building online tools for non-profit 
   organizations, professional societies, and membership-driven organizations. 
   Visit: https://skvare.com for more information.
