# Migration

Once the module is installed (See [README](../../README.md)), log into the CMS. You will see a new section called 'External Content', open it.

In the top-left, you will see a dropdown menu and a 'Create' button. Select the 'Static Site Content Source' option and click 'Create'.

Refresh the page and you will see 'New Connector' in the list of Connectors, click it to open.

Give it a name and enter the base URL, eg, http://www.example.org. If the site you wish to import is a MOSS (Microsoft Office Sharepoint Server) site with /Pages/bla-bla.aspx URLs, select 'MOSS-style URLs' under URL processing, then click save.

Go to the "Crawl" tab and click "Crawl site". Leave it running. It will take some time, depending on the number of pages and assets in the site being crawled.

## Protips

If you reopen the Connector admin in a different browser (so that it has a different session cookie), you can see the current status of the crawling process.
 
If you're using Firebug or Chrome, ensure you have the debugger open before you set the crawl off. Occassionally the crawl will die for unknown reasons, and this will help in debugging.
 
If the host you're running the module is behind a proxy, enable the following in mysite/_config/config.yml

	StaticSiteContentExtractor:
	  curl_opts_proxy:
	    hostname: 'my-gateway.co.nz'
	    port: 1234

Add the following to mysite/_config/config.yml to enable the debug log for link-crawling and importing:

	StaticSiteContentExtractor:
	  log_file: /var/tmp/crawl-and-import.log

## Next Steps

Once the crawling is complete (A message will show in the CMS UI), you'll see all the URLs laid out underneath the connector in tree hierarchy. The URL structure (i.e., where the slashes are) is used to build a hierarchy of URLs.

Now it's time to write some CSS selectors to query different pieces of content for the import. Go to the Main tab of the connector and click the "Add Schema" button. Fill out the fields as follows:

	Priority: 1
	URLs applied to: .*
	DataType: Page

Now click the "Add Rule" button, then immediately click "Save" - this allows you to select from the "Field Name" dropdown menu.

Specify a field to import into - usually "Title" or "Content"
  * Specify a CSS selector e.g. #content h1
  * If you have different CSS selectors for different pages, create multiple Import Rules. The first one that actually returns content will be used.
  
Open sample pages in the tree on the left and you will be able to preview whether the Import Rules work. If they don't work, debug them.

Using simple CSS selectors you can control what part of each remote page is mapped to a particular field within the `SiteTree` class.

Select a base page to import onto. Sometimes it's helpful to create an "imported contnet" page in the Pages section of the CMS first.

Press "Start Importing" (See Protip #2, above). This will also take a long while and doesn't have a robust resume functionality. That's on the to-do list.

That's it! There are quite a few steps but it's easier than copy & pasting all those pages.

### Schema

Schema is the name given to the collection of rules that comprise how a crawled website has its markup formatted and stored in SilverStripe's DataObjects during markup.

Each rule in a schema hinges on a CSS selector that defines the content area on a specific page of the crawled site, and the respective DataObject field within SilverStripe
where this content should be stored.

#### Schema Urls

The schema field 'URLs Applied to' is where you define preg_match regular expressions to match urls from the legacy site to the imported DataTypes in the new site.
Each url is matched against the absolute urls crawled from the legacy site, so you'll need to include the protocol and domain in your urls patterns to make them absolute as well, e.g.
		http://www.legacysite.com/news/.*

The actual preg_match expression is located in staticsiteconnector/code/StaticSiteContentSource.php in the function schemaCanParseURL

	if(preg_match("|^$appliesTo|", $url) == 1) {

#### Schema Priority

Priority order of your schemas is important, the 'Applies To' url patterns are matched against the imported urls in priority order until the first matching schema is found.
This means you need to order your schemas with the most specific patterns first (e.g. `CustomNewsPage`, `NewsPage`, `NewsHolder` etc), then gradually filtering down the priority order to the default catch-all patterns for `Page`, `Image` and `File`.

The default catch-all patterns are:
	(Url Applies To | Data Type | Mime-types)

	.* 		Page  	text/html

	.* 		Image 	image/png
					image/jpeg
					image/gif

	.* 		File  	application/vnd.ms-excel
					application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
					application/msword
					application/pdf
					application/vnd.ms-powerpoint
					application/xml

#### Example Rules:

__Notes:__

* There is an (example SQL dump)[docs/en/example.sql] (MySQL/MariaDB only) included to get you up and running quickly.
* The example below is based on your import using a subclass of `SiteTree`

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

## Migration Post-Processing

After the import has completed, the content will most likely contain urls and asset source paths that reference static urls on the legacy site.

### Static Site Link Rewriting

This task replaces static site urls in the imported pages, replacing the src & href attributes of links, images and files with CMS shortcodes to imported assets and pages.

See: 

	staticsiteconnector/code/tasks/StaticSiteRewriteLinksTask.php

For hints on usage, run the task from the command-lne without any arguments.

To enable output logging for this task, edit your environment configuration file (see: mysite/_config/config.yml) and add the following:

  StaticSiteRewriteLinksTask
    log_file: '/var/tmp/rewrite_links.log'

Note: you need to manually create the log file and make sure the webservice can write to it, e.g.

	#> touch /var/tmp/rewrite_links.log && chmod 766 /var/tmp/rewrite_links.log

#### Notes

If enabled in the `exernal-content` "Import" section, this task can be run automatically once the import itself has completed. This is useful for SilverStripe setups where you may not have shell access to the server.

There is a comprehensive CMS report "Imported links rewrite report" which you can use to analyse your imports and rewritten links, to guide you in tweaking your crawl and import rules which help in pointing out exactly what's failing and to fix it manually if need be.