# ToDo

This is non-priority (i.e. random) and inexhaustive list of tasks that we'd like to see completed to really polish this module.
Some are already registered as issues on Github, others are more in the "would like to have" basket, others may not make it at all:

## Issues

* Add a "Description" field to each schema. Allows users to outline/describe what content from the external site's page-content, each rule refers to.
* Add user help-text or hint explaining what the "Show content in menus" checkbox does.
* [DONE] Add an "Add Rule" button to schema admin UI, so users needn't go back to add another rule.
* In addition to the "Number of URLs" total under the "Crawl" tab, modify to show a list of totals for each mime-type or SS type (e.g. SiteTree)
* Fix the UI under the "Import" tab to store saved values. Currently you will lose your changes if you move away from the "Import" tab and then go back to it.
 * N.b this is an issue with the externalcontent module
* Make the CMS UI reload when users click the "Create" button.
* Add a $description static to `ExternalContentSource` in the external-content module for displaying in the "Create" dropdown instead of the classname
* [WONTFIX] Rename the "Schemas" GridField to "Import Schemas" and move it under the "Import" tab.
* After selecting the the "Crawl site", its label should switch to read "Crawling site..". Maybe show the CMS default "timer" icon too.
* [DONE] Modify `StaticSiteRewriteLinksTask` to rewrite the necessary links to images & documents as well as pages into SS's shortcode system
* Change the label in the default "Connector" screen from "Name" to "Connector Name"
* Either remove the "Folder to import into" dropdown from the CMS UI or use its value instead of the "hard-coded" value taken from the cache-dir
* Some URLs are being crawled and displayed badly encoded as /%2fabout-us%2f.
 * "%2f" is simply a urlencoded "/", so suspect some URLs may be doubles in the crawled site and the module is not dealing with them correctly.
* Hide the "Schema" field on each import rule CMS UI, it is not needed.
* [DUPLICATE] Menus and checkboxes in Schema rule admin don't maintain their values.
* Add a default to "Select how duplicate items should be handled" radio buttons field
* Use `MimeTypeProcessor::get_mime_for_ss_type()` to render a drop down in the schema CMS UI of 'File','SiteTree','Image' which then "auto-maps" the necessary mimes
* Add a schema export function for use in between similar sites hosted on multi/subsite CMS systems like SilverStripe and eZPublish for example.
* [DONE] When creating a file/image schema, CSS selectors are obviously redundant. Remove the add rule Gridfield and use requireDefaultRecords() to auto-create with just a "Filename" field.
* Bug when using MOSS strategy and no "www." prefix. URLs appear as t.nz in "urls" cache file
* Show only a partial tree under each "Connector" as larger lists from large crawls, slow down the CMS considerably (Firefox OS/X, likely others also)
* Instead of defining Mime-Types on a schema:
 * Pre-create 3 default import types: `PageImporter extends Page`, `DocImporter extends File` and `ImageImporter extends Image`
 * On each class, define the matching mime-types
 * In the Schema admin, remove the free-text Mime-Type textarea and replace the "DataType" dropdown with a dropdown comprising one of these three types
 * This should make it far easier and faster to get up and running with the module
* Is the `$rewriter = new StaticSiteLinkRewriter` logic needed in `StaticSiteContentExtractor`? The rewriting logic is now in a BuildTask.
* [DONE] Some bad image encoding is causing errors in the CMS from GD.php - temporarily supressing them by switching to `isTest=1` or prepending '@' to the imagecreatefrom*() functions helps
* tmp files seem to be created for text/html pages when a server error occurs e.g. 400 fix this or write a task that can clean these up
* [DONE] Issues with image/gif - need to see browser errors when these fail
* Some pages in the 'urls' cache file are not being imported
 * They exist in 'urls' cache
 * Importer isn't importing them
 * They are not therefore showing as having their links rewritten
* Add an onAfterImport() (see external-content module) to StaticSiteImporter and run link-rewrite task in there based on user-selection in the CMS (default it to 'yes')
