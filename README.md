SilverStripe Static Site Connector
==================================

This connector extracts content from another site by crawling its HTML, rather than connecting to an internal API. Although this has the disadvantage of leaving it unable to extract any information or structure not represented in the outputted HTML of the site, it requires no special access, nor does it rely on particular back-end systems. This makes it suited for experimental site imports, as well as connections to more obscure CMSes.

It works in the following way:

 * A list of URLs are extracted from the site using PHPCrawl, and cached.
 * Each URL corresponds to an imported page, using the presence of "/" and "?" in the URL to build the heirarchy.
 * Page content is imported page-by-page using CURL, and content elements extracted with CSS selectors.  phpQuery is used for this purpose.


Installation
------------

This module requires the PHP Sempahore functions.  These are installed by default on Debian PHP distributions, but if you are using Macports you will need to add the `+ipc` flag when installing php5:

    sudo port install php5 +apache2 +ipc


License
-------

This code is available under the BSD license, with the exception of the library PHPCrawl, bundled with this module, which is GPL version 2.