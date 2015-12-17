<?php

/**
 * External content - run import as a build task, importing content into a new container
 */
class ExternalContentImportContentTask extends BuildTask
{

    public function run($request)
    {
        $id = $request->getVar('ID');
        if ((!is_numeric($id) && !preg_match('/^[0-9]+_[0-9]+$/', $id)) || !$id) {
            echo "<p>Specify ?ID=(number) or ?ID=(ID)_(Code)</p>\n";
            return;
        }

        $includeSelected        = false;
        $includeChildren        = true;
        $duplicates            = 'Duplicate';
        $selected                = $id;

        $target = new Page;
        $target->Title = "Import on " . date('Y-m-d H:i:s');
        $target->write();
        $targetType = 'SiteTree';

        $from = ExternalContent::getDataObjectFor($selected);
        if ($from instanceof ExternalContentSource) {
            $selected = false;
        }

        $importer = null;
        $importer = $from->getContentImporter($targetType);

        if ($importer) {
            $importer->import($from, $target, $includeSelected, $includeChildren, $duplicates);
        }
    }
}
