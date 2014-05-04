# ToDo

This is non-priority (i.e. random) and inexhaustive list of tasks that we'd like to see completed to really polish this module.
Some are already registered as issues on Github, others are more in the "would like to have" basket, others may not make it at all:

## StaticSiteConnector Module Issues (in order of severity)

* BUG: Import sometimes fails with console-error ("Method 'publish' doesn't exist on Image") seems to occur occasionally if a folder of assets already exists, and a new import is run.
 * The above errors seem to only occur after an import failed with an error and is then manually resumed.
* BUG: If assets import directory isn't writable, a File DB record is still created even with a non-imported file binary object.
* BUG: Can only crawl VHosts. Websites located on a subdirectory e.g. http://localhost/mysite are only partially crawled.
* BUG: Lists of crawled URLs from large crawls (1000+ pages), slow down the CMS considerably. Suggest show only partial tree under each "Connector" in the crawl tab (or optimise existing and problematic CMS JS)
* BUG: Travis is failing for env: DB=PGSQL CORE_RELEASE=3.1
* BUG: StaticSiteImportDataObject::current will only return the correct object for the user that created it. This is too restrictive.
* TASK: Is StaticSiteCrawlURLsTask needed anymore?
* TASK: Replace relevant StaticSiteMimeTypeProcessor logic with logic found in Zend_Validate_File_ExcludeMimeType.
* TASK: Translation: Ensure all messages are rendered through _t()
* TASK: Ensure CSV export button works properly in FailedLinksRewriteReport
* TASK: Selecting "external content" in the CMS for the first time, shows nothing in the main pane. Show a default connector (e.g. the first) by default.
* TASK: Make the "Clear imports" logic, specific to the selected import e.g. add ExternalContentID field to StaticSiteImportDataObject
* TASK: If "Automatically run link-rewrite task" is checked, add more detail to "Import completed" message.
* TASK: Make files save using the same directory hierarchy as the legacy/scraped site. See StaticSiteFileTransformer::getParentDir()
* ENHANCEMENT: Add "Link rewrite task was run automatically. "[View failed URL rewrite report"]" confirmation text to "successful import" message
* ENHANCEMENT: Add a "Description" field to each schema. Allows users to outline/describe what content from the external site's page-content, each rule refers to.
* ENHANCEMENT: In addition to the "Number of URLs" total under the "Crawl" tab, modify to show a list of totals for each mime-type or SS type (e.g. SiteTree)
* ENHANCEMENT: Add schema export feature for use between SilverStripe installs e.g. CWP
* ENHANCEMENT: Add CMS UI to allow fine-grained control of the sleep time between server hits. See usleep() in StaticSite#Transformer#transform()
* ENHANCEMENT: Create a multi-select dropdown menu that comprises data from framework/_config/mimetypes.yml
* ENHANCEMENT: Use php-diff lib via composer as the arbiter of change in the StaticSiteUrlRewriteTask::run() method

## External Content Module Issues:

* BUG: Fix the UI under the "Import" tab to store saved values. Currently you will lose your changes if you move away from the "Import" tab and then go back to it.
* [PR#17] ENHANCEMENT: Add a default to "Select how duplicate items should be handled" radio buttons field
* Logic found in StaticSiteTransformResult class should really exist in the external-content module itself.
