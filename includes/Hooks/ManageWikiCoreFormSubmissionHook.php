<?php

namespace WikiForge\ManageWiki\Hooks;

use IContextSource;
use WikiForge\CreateWiki\RemoteWiki;
use Wikimedia\Rdbms\DBConnRef;

interface ManageWikiCoreFormSubmissionHook {
	/**
	 * @param IContextSource $context
	 * @param string $dbName
	 * @param DBConnRef $dbw
	 * @param array $formData
	 * @param RemoteWiki &$wiki
	 * @return void
	 */
	public function onManageWikiCoreFormSubmission( $context, $dbName, $dbw, $formData, &$wiki ): void;
}
