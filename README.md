Box.com Long OAuth2 Expire
==========================

This application is an example of when using the refresh_token the expires is
reset to a large number e.g. [expires] => 1398295382

The real problem here is box.com returns 'expires' instead of 'expire_at'

Reproduce
---------
1. Copy config/autoload/local.php.dist to config/autoload/local.php 
   and edit with OAuth2 key and secret for a valid connection.

2. Download and run Composer (https://getcomposer.org/)

3. Run bin/fetchOAuth2
   Open a browser to http://localhost:8081 
   This will run an OAuth2 request through box.com and return
   a command line string with the Access Token and Refresh Token as parameters.

4. After a successful run which says "Access Token is valid" run the same command
   from the command line but change the first character of the Access Token to a
   different value.  This will trigger the Refresh Token code.

5. The app will show the returned grant inclulding a long expires.
