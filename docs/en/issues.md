# ToDo

This is non-priority (i.e. random) and inexhaustive list of tasks that we'd like to see completed to really polish this module.
Some are already registered as issues on Github, others are more in the "would like to have" basket, others may not make it at all:

## StaticSiteConnector Module Issues (in order of severity/importance)

* BUG: Import sometimes fails with console-error ("Method 'publish' doesn't exist on Image") seems to occur occasionally if a folder of assets already exists, and a new import is run.
 * The above error seems to only occur after an import failed with an error and is then manually resumed.
* BUG: Can only crawl VHosts. Websites located on a subdirectory e.g. http://localhost/mysite are only partially crawled.
* BUG: Lists of crawled URLs from large crawls (1000+ pages), slow down the CMS considerably. Suggest show only partial tree under each "Connector" in the crawl tab (or optimise existing and problematic CMS JS)
* BUG: link-rewriting fails when there are multiple images with the same value for <DataType>.StaticSiteUrl
* BUG: When selecting the "duplicate" duplication strategy, multiple DB-entries for files are created, but not multiple images on the f/s
 * Implemented some file-name versioning, and the new version is created and renamed, but the original disappears.
 * StaticSiteFileTransformerTest is failing
* BUG: Project won't build properly from composer (While it's not on Packagist)
* TASK: Is StaticSiteCrawlURLsTask needed anymore?
* TASK: Translation: Ensure all messages are rendered through _t()
* TASK: Selecting "external content" in the CMS for the first time, shows nothing in the main pane. Show a default connector (e.g. the first) by default.
* TASK: If "Automatically run link-rewrite task" is checked, add more detail to "Import completed" message.
* TASK: Add new filter expression as per `FileNameFilter` to module _config instead of using str_replace() in StaticSiteFIleTransformer::buildFileProperties()
* ENHANCEMENT: Add "Link rewrite task was run automatically. "[View failed URL rewrite report"]" confirmation text to "successful import" message
* ENHANCEMENT: Add a "Description" field to each schema. Allows users to outline/describe what content from the external site's page-content, each rule refers to.
* ENHANCEMENT: In addition to the "Number of URLs" total under the "Crawl" tab, modify to show a list of totals for each mime-type or SS type (e.g. SiteTree)
* ENHANCEMENT: Add schema export feature for use between SilverStripe installs e.g. CWP
* ENHANCEMENT: Create a multi-select dropdown menu that comprises data from framework/_config/mimetypes.yml
* ENHANCEMENT: Use php-diff lib via composer as the arbiter of change in the StaticSiteUrlRewriteTask::run() method

## External Content Module Issues:

* BUG: Fix the UI under the "Import" tab to store saved values. Currently you will lose your changes if you move away from the "Import" tab and then go back to it.
* [PR#17] ENHANCEMENT: Add a default to "Select how duplicate items should be handled" radio buttons field
* Logic found in StaticSiteTransformResult class should really exist in the external-content module itself.
