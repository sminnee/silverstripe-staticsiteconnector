SilverStripe Static Site Connector
==================================

This connector extracts content from another site by crawling its HTML, rather than connecting to an internal API.
Although this has the disadvantage of leaving it unable to extract any information or structure not represented in the outputted HTML of the site, it requires no special access, nor does it rely on particular back-end systems.
This makes it suited for experimental site imports, as well as connections to more obscure CMS's.

It works in the following way:

 * A list of URLs are extracted from the site using [PHPCrawl](http://cuab.de/), and cached.
 * Each URL corresponds to an imported page, using the presence of "/" and "?" in the URL to build the .
 * Page content is imported page-by-page using CURL, and content elements extracted with CSS selectors. [phpQuery](http://code.google.com/p/phpquery/) is used for this purpose.

Installation
------------

This module requires the [PHP Sempahore](http://php.net/manual/en/book.sem.php) functions. These are installed by default on Debian PHP distributions, but if you're using Macports you'll need to add the `+ipc` flag when installing php5:

    sudo port install php5 +apache2 +ipc

If compiling PHP from source you need to pass three additional flags to the configure script:

	./configure <usual flags> '--enable-sysvsem' '--enable-sysvshm' '--enable-sysvmsg'

Once that's done, you can use Composer to add the module to your SilverStripe project:

    composer require silverstripe/staticsiteconnector

Finally, visit `/dev/build` on your site to update the database schema.

Migration
---------

 * After you have installed the module, log into the CMS. You will see a new section called 'External Content', open it.

 * In the top-left, you will see a dropdown menu and a 'Create' button. Select the 'StaticSiteContentSource' option and click 'Create'.

 * Refresh the page and you will see 'New Connector' in the list of Connectors, click it to open.

 * Give it a name and enter the base URL, eg, http://example.org. If your site is a MOSS (Microsoft SharePoint Server) site with /Pages/bla-bla.aspx URLs, select 'MOSS-style URLs' under URL processing, then click save.

 * Go to the Crawl tab and click "Crawl site". Leave it running. It will take some time. Protip: If you reopen the Connector admin in a different browser (so that it has a different session cookie), you can see the current status of the crawling process.

 * Once the crawling is complete, you'll see all the URLs laid out underneath the connector. The URL structure (i.e., where the slashes are) is used to build a hierarchy of URLs.

 * Now it's time to write some CSS selectors to query different pieces of content.

	* Go to the Main tab of the connector and click the "Add Schema" button.

	* Fill out the fields as follows:

		* Priority: 1000000
		* URLs applied to: .*
		* DataType: Page

	* Now click the "Add Rule" button, then immediately click "Save" - this allows you to select from the "Field Name" dropdown menu

		* Specify a field to import into - usually "Title" or "Content"
		* Specify a CSS selector
		* If you have different CSS selectors for different pages, create multiple Import Rules. The first one that actually returns content will be used.

 * Open sample pages in the tree on the left and you will be able to preview whether the Import Rules work. If they don't work, debug them.

 * When you're happy, open the Connector and go to the Import tab.

 * Select a base page to import onto. Sometimes it's helpful to create an "imported contnet" page in the Pages section of the CMS first.

 * Press "Start Importing". This will also take a long while and doesn't have a robust resume functionality. That's on the to-do list.

That's it! There are quite a few steps but it's easier than copy & pasting all those pages.

### Schema

Schema is the name given to the collection of rules that comprise how a crawled website has its markup formatted and stored in SilverStripe's DataObjects during markup.

Each rule in a schema hinges on a CSS selector that defines the content area on a specific page of the crawled site, and the respective DataObject field within SilverStripe
where this content should be stored.

#### Examples Rules:

__Note:__ This example is based on your import using a subclass of `SiteTree`

##### Title

This rule takes the content of the crawled-site's &lt;h1&gt; element, imports it into the `SiteTree.Title` field which forms your imported page's &lt;title&gt; element.

* __Field Name:__ `Title`
* __CSS Selector:__ `h1`
* __Exclude CSSSelector:__ Optional
* __Element attribute:__ Optional
* __Convert to plain text:__ Check this box to remove any/all markup found in the crawled site
* __Schema:__ Select "Page" or your custom SilverStripe DataObject to import content into

##### MenuTitle

This rule takes the content of the crawled-site's &lt;h1&gt; element, imports it into the `SiteTree.MenuTitle` field. This is used in the CMS' SiteTree list.

* __Field Name:__ `MenuTitle`
* __CSS Selector:__ `h1`
* __Exclude CSSSelector:__ Optional
* __Element attribute:__ Optional
* __Convert to plain text:__ Check this box to remove any/all markup found in the crawled site
* __Schema:__ Select "Page" or your custom SilverStripe DataObject to import content into

##### Content

This rule takes the content of the crawled-site's main body content (excluding any &lt;h1&gt; elements) - in this example we pretend it's all wrapped in a div#content element.
This will then form the content that is used in the `SiteTree.Content` field.

* __Field Name:__ `Content`
* __CSS Selector:__ `div#content`
* __Exclude CSSSelector:__ `h1`
* __Element attribute:__ Optional
* __Convert to plain text:__ Leave this unchecked, you'll probably want to keep all the crawled site's markup as it's being imported into an HTMLText fieldtype - eventually editable in the CMS via the WYSIWYG editor
* __Schema:__ Select "Page" or your custom SilverStripe DataObject to import content into

#### Meta - Description

This rule will collect the contents of a crawled-page's &lt;meta&gt; (description) element and imports it into the `SiteTree.MetaDescription` field.
You can obviously adapt this to suit other &lt;meta&gt; elements you wish to import.

* __Field Name:__ `MetaDescription`
* __CSS Selector:__ `meta[name=description]`
* __Exclude CSSSelector:__ 
* __Element attribute:__ `value`
* __Convert to plain text:__ Check this box to remove any/all markup found in the crawled site (v.unlikely!)
* __Schema:__ Select "Page" or your custom SilverStripe DataObject to import content into


License
-------

This code is available under the BSD license, with the exception of the [PHPCrawl](http://cuab.de/) library, bundled with this module which is GPL version 2.
