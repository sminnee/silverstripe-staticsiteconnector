<?php

class StaticSiteImporter extends ExternalContentImporter
{
    public function __construct()
    {
        $this->contentTransforms['sitetree'] = new StaticSitePageTransformer();
    }

    public function getExternalType($item)
    {
        return "sitetree";
    }
}
