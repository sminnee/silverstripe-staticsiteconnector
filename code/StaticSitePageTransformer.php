<?php

class StaticSitePageTransformer implements ExternalContentTransformer {

	public function transform($item, $parentObject, $duplicateStrategy) {
		$page = new Page;

		$page->ParentID = $parentObject ? $parentObject->ID : 0;
		$page->Title = $item->Name;
		$page->MenuTitle = $item->Name;
		$page->write();

		return new TransformResult($page, $item->stageChildren());
	}
}