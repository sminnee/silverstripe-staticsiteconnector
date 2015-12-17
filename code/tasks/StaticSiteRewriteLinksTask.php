<?php

/**
 * Rewrite all links in content imported via staticsiteimporter
 */
class StaticSiteRewriteLinksTask extends BuildTask
{
    
    public function run($request)
    {
        $id = $request->getVar('ID');
        if (!is_numeric($id) || !$id) {
            echo "<p>Specify ?ID=(number)</p>";
            return;
        }

        // Find all pages
        $contentSource = StaticSiteContentSource::get()->byID($id);
        $pages = $contentSource->Pages();

        echo "<p>Looking through " . $pages->Count() . " pages</p>\n";

        // Set up rewriter
        $pageLookup = $pages->map('StaticSiteURL', 'ID');
        $baseURL = $contentSource->BaseUrl;

        $rewriter = new StaticSiteLinkRewriter(function ($url) use ($pageLookup, $baseURL) {
            $fragment = "";
            if (strpos($url, '#') !== false) {
                list($url, $fragment) = explode('#', $url, 2);
                $fragment = '#'.$fragment;
            }

            if ($pageLookup[$url]) {
                return '[sitetree_link,id='.$pageLookup[$url] .']' . $fragment;
            } else {
                if (substr($url, 0, strlen($baseURL)) == $baseURL) {
                    echo "<p>WARNING: $url couldn't be rewritten.</p>\n";
                }
                return $url . $fragment;
            }
        });

        // Perform rewriting
        $changedFields = 0;
        foreach ($pages as $page) {
            $schema = $contentSource->getSchemaForURL($page->URLSegment);
            // Get fields to process
            $fields = array();
            foreach ($schema->ImportRules() as $rule) {
                if (!$rule->PlainText) {
                    $fields[] = $rule->FieldName;
                }
            }
            $fields = array_unique($fields);
            

            foreach ($fields as $field) {
                $newContent = $rewriter->rewriteInContent($page->$field);
                if ($newContent != $page->$field) {
                    $newContent = str_replace(array('%5B', '%5D'), array('[', ']'), $newContent);
                    $changedFields++;

                    echo "<p>Changed $field on $page->Title (#$page->ID).</p>";
                    $page->$field = $newContent;
                }
            }

            $page->write();
        }
        echo "<p>DONE. Amended $changedFields content fields.</p>".PHP_EOL;
    }
}
