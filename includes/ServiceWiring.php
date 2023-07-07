<?php

use MediaWiki\MediaWikiServices;
use WikiForge\ManageWiki\Hooks\ManageWikiHookRunner;

return [
	'ManageWikiHookRunner' => static function ( MediaWikiServices $services ): ManageWikiHookRunner {
		return new ManageWikiHookRunner( $services->getHookContainer() );
	},
];
