# ToDo

This is non-priority (i.e. random) and inexhaustive list of tasks that we'd like to see completed to really polish this module.
Some are already registered as issues on Github, others are more in the "would like to have" basket, others may not make it at all:

## StaticSiteConnector Module Issues (in order of severity)

* BUG: Import sometimes fails with console-error (method 'publish' doesn't exist on Image) if a folder of assets already exists, and a new import is run.
* BUG: Import sometimes fails with console-error (rename /tmp/tmpABCD to assets/Import/blah.gif: Permission Denied) if a folder of assets already exists, and a new import is run.
* BUG: Import sometimes fails with console-error (cannot move assets/Import/blah.gif to assets/Import/blah.gif - assets/Import/blah.gif doesn't exist) if a folder of assets already exists, and a new import is run.
 * Seems to occur if an import was stopped halfway due to another error and then manually resumed.
* BUG: Can only crawl VHosts. Websites located on a subdirectory e.g. http://localhost/mysite are only partially crawled.
* BUG: Show only partial tree under each "Connector" in the crawl tab. Lists from large crawls (1000+ pages), slow down the CMS considerably (Firefox OS/X, likely others also)
* BUG: MimeType Processing is buggy when a zero-length mime-type is encountered in legacy site's (incoming) URLs.
* TASK: Is StaticSiteCrawlURLsTask needed anymore?
* TASK: Replace relevant StaticSiteMimeTypeProcessor logic with logic found in Zend_Validate_File_ExcludeMimeType.
* TASK: Translation: Ensure all messages are rendered through _t()
* TASK: Ensure CSV export button works properly in FailedLinksRewriteReport
* TASK: Make it more obvious that the Schema config CMS UI is related to "Importing" and _not_ related to "Crawling".
* ENHANCEMENT: Add a "Description" field to each schema. Allows users to outline/describe what content from the external site's page-content, each rule refers to.
* ENHANCEMENT: Add user help-text or hint explaining what the "Show content in menus" checkbox does.
* ENHANCEMENT: In addition to the "Number of URLs" total under the "Crawl" tab, modify to show a list of totals for each mime-type or SS type (e.g. SiteTree)
* ENHANCEMENT: After selecting "Crawl site", its label should switch to read "Crawling site..". Maybe show the CMS default "timer" icon too.
* ENHANCEMENT: Add schema export feature for use between SilverStripe installs e.g. CWP
* ENHANCEMENT: Add CMS UI to allow fine-grained control of the sleep time between server hits. See usleep() in StaticSite#Transformer#transform()
* ENHANCEMENT: Create a multi-select dropdown menu that comprises data from framework/_config/mimetypes.yml
* ENHANCEMENT: Use php-diff lib via composer as the arbiter of change in the StaticSiteUrlRewriteTask::run() method

## External Content Module Issues:

* BUG: Fix the UI under the "Import" tab to store saved values. Currently you will lose your changes if you move away from the "Import" tab and then go back to it.
* [PR#17] ENHANCEMENT: Add a default to "Select how duplicate items should be handled" radio buttons field
* Logic found in StaticSiteTransformResult class should really exist in the external-content module itself.
