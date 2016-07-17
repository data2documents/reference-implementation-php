Data 2 Documents
================


Setup instructions
------------------

1. Create a ‘d2d_system’ directory somewhere on your web server. This could be in your web root directory, but could also be a directory below that, unreachable for web traffic.

2. Place all source files in this directory, either by doing a git clone if possible, or by copying them to the webserver using e.g. FTP.

2. Re-route all requests to the d2d_portal.php script, except for requests targeted directly at specific file extensions. See the file 'example_htaccess' and alter it in according to the location of the d2d_system folder. Thereafter, rename it to '.htaccess' and place it in the root directory of the target site.

This documentation will be extended in the near future. In the meantime, we are happy to help if you have questions. Please send us an email at info@data2documents.org

Acknowledgements
----------------
This source code uses the Easy RDF library, Copyright (c) 2009-2011 by Nicholas J Humfrey. Source files for this library including its full licence reside in the EasyRdf folder.


