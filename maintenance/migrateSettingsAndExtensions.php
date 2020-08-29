<?php

use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ManageWikiMigrateSettingsAndExtensions extends Maintenance {
	private $config;

	public function __construct() {
		parent::__construct();
		$this->config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'managewiki' );
	}

	public function execute() {
		$dbw = wfGetDB( DB_MASTER, [], $this->config->get( 'CreateWikiDatabase' ) );

		$res = $dbw->select(
			'cw_wikis',
			[
				'wiki_dbname',
				'wiki_settings',
				'wiki_extensions'
			]
		);

		foreach ( $res as $row ) {
			$extensionsArray = explode( ',', $row->wiki_extensions );
			$extensions = [];

			foreach ( $extensionsArray as $ext ) {
				if ( isset( $this->config->get( 'ManageWikiExtensions' )[$ext] ) ) {
					$extensions[] = $ext;
				}
			}

			$dbw->insert(
				'mw_settings',
				[
					's_dbname' => $row->wiki_dbname,
					's_settings' => $row->wiki_settings,
					's_extensions' => json_encode( $extensions )
				]
			);
		}
	}
}

$maintClass = 'ManageWikiMigrateSettingsAndExtensions';
require_once RUN_MAINTENANCE_IF_MAIN;
