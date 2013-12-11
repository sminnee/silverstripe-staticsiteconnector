# ToDo

This is non-priority (i.e. random) and inexhaustive list of tasks that we'd like to see completed to really polish this module.
Some are already registered as issues on Github, others are more in the "would like to have" basket, others may not make it at all:

## Issues

* Add a "Description" field to each schema. Allows users to outline/describe what content from the external site's page-content, each rule refers to.
* Add user help-text or hint explaining what the "Show content in menus" checkbox does.
* In addition to the "Number of URLs" total under the "Crawl" tab, modify to show a list of totals for each mime-type or SS type (e.g. SiteTree)
* Fix the UI under the "Import" tab to store saved values. Currently you will lose your changes if you move away from the "Import" tab and then go back to it.
 * N.b this is an issue with the externalcontent module
* Make the CMS UI reload when users click the "Create" button.
* Add a $description static to `GridField to "Import Schemas" and move it under the "Import" tab.
* After selecting the the "Crawl site", its label should switch to read "Crawling site..". Maybe show the CMS default "timer" icon too.
* Change the laxternalContentSource` in the external-content module for displaying in the "Create" dropdown instead of the classname
 * Make that text disappear onfocus if it equals the default text
* Either remove the "Folder to import into" dropdown from the CMS UI or use its value instead of the "hard-coded" value taken from the cache-dir
* Some URLs are being crawled and displayed badly encoded as /%2fabout-us%2f.
 * "%2f" is simply a urlencoded "/", so suspect some URLs may be doubles in the crawled site and the module is not dealing with them correctly.
* Hide the "Schema" field on each import rule CMS UI, it is not needed.
* Add a default to "Select how duplicate items should be handled" radio buttons field
* Use `MimeTypeProcessor::get_mime_for_ss_type()` to render a drop down in the schema CMS UI of 'File','SiteTree','Image' which then "auto-maps" the necessary mimes
* Add a schema export function for use in between similar sites hosted on multi/subsite CMS systems like SilverStripe and eZPublish for example.
* Bug when using no "www." prefix in basic setup. URLs appear as t.nz for example, in "urls" cache file
* Show only a partial tree under each "Connector" in the crawl tab, as larger lists from large crawls, slow down the CMS considerably (Firefox OS/X, likely others also)
* Instead of defining Mime-Types on a schema:
 * Pre-create 3 default import types: `PageImporter extends Page`, `DocImporter extends File` and `ImageImporter extends Image`
 * On each class, define the matching mime-types
 * In the Schema admin, remove the free-text Mime-Type textarea and replace the "DataType" dropdown comprising one of these three types.
* tmp files seem to be created for text/html pages when a server error occurs e.g. 400 fix this or write a task that can clean these up
* Some pages in the 'urls' cache file are not being imported
 * They exist in 'urls' cache
 * Importer isn't importing them
 * They are not therefore showing as having their links rewritten
* Add an onAfterImport() (see external-content module) to StaticSiteImporter and run StaticSiteRewriteLinksTask from it, based on CMS UI user-selection (default is 'yes')
* Add logic to the crawl that allows images used only as CSS background images in a legacy site, to be crawled
* If using the "Duplicate" import strategy. The buildFileProperties() method isn't actually invoked
* If using the "Overwrite" import strategy, nothing gets overwritten. A deletion and re-creation needs to happen first