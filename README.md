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

Once that is done, you can use Composer to add the module to your SilverStripe project.  

    composer require silverstripe/staticsiteconnector

Finally, visit `/dev/build` on your site to update the database schema.

How to use this module to migrate a site's content
--------------------------------------------------

 * After you have installed the module, log into the CMS.  You will see a new section called 'External Content'.  Open it.

 * In the top-left, you will see a dropdown and 'Create' button.  Select 'StaticSiteContentSource' and click 'Create'.

 * Refresh the page and you will see 'New Connector' in the list of Connectors.  Click it to open.

 * Give it a name and enter the base URL, eg, http://example.org.  If your site is a MOSS sites with /Pages/bla-bla.aspx URLs, select 'MOSS-style URLs' under URL processing. Click save.

 * Go to the Crawl tab and click "Crawl site".  Leave it running.  It will take some time.  As a trick, if you reopen the Connector admin in a different browser (so that it has a different session cookie), you can see the current status of the crawling.

 * Once the crawling is complerte, you will see all the URLs laid out underneath the connector.  The URL structure (i.e., where the slashes are) is used to build a heirarchy of URLs.  You can preview the contnet

 * Now it is time to write CSS selectors to query different pieces of content.  Go to the Main tab of the connector.  Under "Import Rules", click Add Rulel.

    * Specify a field to import into - usually Title or Content
    * Specify a CSS selector
    * If you have different CSS selectors for different pages, create multiple Import Rules.  The first one that actually returns content will be used.

 * Open sample pages in the tree on the left and you will be able to preview whether the Import Rules work.  If they don't work, debug them.

 * When you're happy, open the Connector and go to the Import tab.

 * Select a base page to import onto.  Sometimes it's helpful to create an "imported contnet" page in the Pages section of the CMS first.

 * Press "Start Importing".  This will also take a long while and doesn't have a robust resume functionality.  That's on the to-do list.

That's it!  There are quite a few steps but it's easier than copy & pasting all those pages.

License
-------

This code is available under the BSD license, with the exception of the library PHPCrawl, bundled with this module, which is GPL version 2.
