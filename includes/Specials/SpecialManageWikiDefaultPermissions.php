<?php

namespace WikiForge\ManageWiki\Specials;

use Config;
use ErrorPageError;
use GlobalVarConfig;
use Html;
use HTMLForm;
use ManualLogEntry;
use MediaWiki\MediaWikiServices;
use SpecialPage;
use UserGroupMembership;
use WikiForge\CreateWiki\CreateWikiJson;
use WikiForge\CreateWiki\RemoteWiki;
use WikiForge\ManageWiki\FormFactory\ManageWikiFormFactory;
use WikiForge\ManageWiki\Helpers\ManageWikiPermissions;
use WikiForge\ManageWiki\Hooks;
use WikiForge\ManageWiki\ManageWiki;

class SpecialManageWikiDefaultPermissions extends SpecialPage {
	/** @var Config */
	private $config;

	public function __construct() {
		parent::__construct( 'ManageWikiDefaultPermissions' );
		$this->config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'managewiki' );
	}

	public function canModify() {
		if ( !MediaWikiServices::getInstance()->getPermissionManager()->userHasRight( $this->getContext()->getUser(), 'managewiki-editdefault' ) ) {
			return false;
		}

		return true;
	}

	public function getDescription() {
		return $this->msg( $this->canModify() ? 'managewikidefaultpermissions' : 'managewikidefaultpermissions-norights' )->text();
	}

	public function execute( $par ) {
		$this->setHeaders();
		$out = $this->getOutput();
		$globalwiki = $this->config->get( 'CreateWikiGlobalWiki' );

		if ( !ManageWiki::checkSetup( 'permissions' ) ) {
			throw new ErrorPageError( 'managewiki-unavailable', 'managewiki-disabled', [ '1' => 'permissions' ] );
		}

		if ( $par != '' && ( $globalwiki == $this->config->get( 'DBname' ) ) ) {
			$this->getOutput()->addBacklinkSubtitle( $this->getPageTitle() );
			$this->buildGroupView( $par );
		} else {
			$this->buildMainView();
		}
	}

	public function buildMainView() {
		$canModify = $this->canModify();
		$globalwiki = $this->config->get( 'CreateWikiGlobalWiki' );

		$out = $this->getOutput();
		$out->addModules( [ 'mediawiki.special.userrights' ] );

		if ( $globalwiki == $this->config->get( 'DBname' ) ) {
			$mwPermissions = new ManageWikiPermissions( 'default' );
			$groups = array_keys( $mwPermissions->list() );
			$craftedGroups = [];

			foreach ( $groups as $group ) {
				$craftedGroups[UserGroupMembership::getGroupName( $group )] = $group;
			}

			$groupSelector = [];

			$groupSelector['info'] = [
				'default' => $this->msg( 'managewikidefaultpermissions-select-info' )->text(),
				'type' => 'info',
			];

			$groupSelector['groups'] = [
				'label-message' => 'managewiki-permissions-select',
				'type' => 'select',
				'options' => $craftedGroups,
			];

			$selectForm = HTMLForm::factory( 'ooui', $groupSelector, $this->getContext(), 'groupSelector' );
			$selectForm->setWrapperLegendMsg( 'managewiki-permissions-select-header' );
			$selectForm->setMethod( 'post' )->setFormIdentifier( 'groupSelector' )->setSubmitCallback( [ $this, 'onSubmitRedirectToPermissionsPage' ] )->prepareForm()->show();

			if ( $canModify ) {
				$createDescriptor = [];

				$createDescriptor['info'] = [
					'type' => 'info',
					'default' => $this->msg( 'managewikidefaultpermissions-create-info' )->text(),
				];

				$createDescriptor['groups'] = [
					'type' => 'text',
					'label-message' => 'managewiki-permissions-create',
					'validation-callback' => [ $this, 'validateNewGroupName' ],
				];

				$createForm = HTMLForm::factory( 'ooui', $createDescriptor, $this->getContext() );
				$createForm->setWrapperLegendMsg( 'managewiki-permissions-create-header' );
				$createForm->setMethod( 'post' )->setFormIdentifier( 'createForm' )->setSubmitCallback( [ $this, 'onSubmitRedirectToPermissionsPage' ] )->prepareForm()->show();
			}
		} elseif ( !( $globalwiki == $this->config->get( 'DBname' ) ) && !$canModify ) {
				throw new ErrorPageError( 'managewiki-unavailable', 'managewiki-unavailable-notglobalwiki' );
		}

		if ( !( $globalwiki == $this->config->get( 'DBname' ) ) && $canModify ) {
			$out->setPageTitle( $this->msg( 'managewiki-permissions-resetgroups-title' )->plain() );

			$resetPermissionsDescriptor = [];

			$resetPermissionsDescriptor['info'] = [
				'type' => 'info',
				'default' => $this->msg( 'managewiki-permissions-resetgroups-header' )->parse(),
			];

			$resetPermissionsForm = HTMLForm::factory( 'ooui', $resetPermissionsDescriptor, $this->getContext() );
			$resetPermissionsForm->setWrapperLegendMsg( 'managewikidefaultpermissions-resetgroups-header' );
			$resetPermissionsForm->setMethod( 'post' )->setFormIdentifier( 'resetpermissionsform' )->setSubmitTextMsg( 'managewiki-permissions-resetgroups' )->setSubmitDestructive()->setSubmitCallback( [ $this, 'onSubmitPermissionsResetForm' ] )->prepareForm()->show();

			$resetSettingsDescriptor = [];

			$resetSettingsDescriptor['info'] = [
				'type' => 'info',
				'default' => $this->msg( 'managewiki-permissions-resetsettings-header' )->parse(),
			];

			$resetSettingsForm = HTMLForm::factory( 'ooui', $resetSettingsDescriptor, $this->getContext() );
			$resetSettingsForm->setWrapperLegendMsg( 'managewikidefaultpermissions-resetsettings-header' );
			$resetSettingsForm->setMethod( 'post' )->setFormIdentifier( 'resetsettingsform' )->setSubmitTextMsg( 'managewiki-permissions-resetsettings' )->setSubmitDestructive()->setSubmitCallback( [ $this, 'onSubmitSettingsResetForm' ] )->prepareForm()->show();

			$resetCacheDescriptor = [];

			$resetCacheDescriptor['info'] = [
				'type' => 'info',
				'default' => $this->msg( 'managewiki-permissions-resetcache-header' )->parse(),
			];

			$resetCacheForm = HTMLForm::factory( 'ooui', $resetCacheDescriptor, $this->getContext() );
			$resetCacheForm->setWrapperLegendMsg( 'managewikidefaultpermissions-resetcache-header' );
			$resetCacheForm->setMethod( 'post' )->setFormIdentifier( 'resetcacheform' )->setSubmitTextMsg( 'managewiki-permissions-resetcache' )->setSubmitDestructive()->setSubmitCallback( [ $this, 'onSubmitCacheResetForm' ] )->prepareForm()->show();

		}
	}

	public function onSubmitRedirectToPermissionsPage( array $params ) {
		header( 'Location: ' . SpecialPage::getTitleFor( 'ManageWikiDefaultPermissions' )->getFullURL() . '/' . $params['groups'] );

		return true;
	}

	public function onSubmitPermissionsResetForm( $formData ) {
		$out = $this->getOutput();

		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()
			->getMainLB( $this->config->get( 'CreateWikiDatabase' ) )
			->getMaintenanceConnectionRef( DB_PRIMARY, [], $this->config->get( 'CreateWikiDatabase' ) );

		$dbw->delete(
			'mw_permissions',
			[
				'perm_dbname' => $this->config->get( 'DBname' )
			],
			__METHOD__
		);

		$cwConfig = new GlobalVarConfig( 'cw' );
		Hooks::onCreateWikiCreation( $this->config->get( 'DBname' ), $cwConfig->get( 'Private' ) );

		$logEntry = new ManualLogEntry( 'managewiki', 'rights-reset' );
		$logEntry->setPerformer( $this->getContext()->getUser() );
		$logEntry->setTarget( SpecialPage::getTitleValueFor( 'ManageWikiDefaultPermissions' ) );
		$logEntry->setParameters( [ '4::wiki' => $this->config->get( 'DBname' ) ] );
		$logID = $logEntry->insert();
		$logEntry->publish( $logID );

		$out->addHTML(
			Html::successBox(
				Html::element(
					'p',
					[],
					$this->msg( 'managewiki-success' )->plain()
				),
				'mw-notify-success'
			)
			);

		return false;
	}

	public function onSubmitSettingsResetForm( $formData ) {
		$out = $this->getOutput();

		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()
			->getMainLB( $this->config->get( 'CreateWikiDatabase' ) )
			->getMaintenanceConnectionRef( DB_PRIMARY, [], $this->config->get( 'CreateWikiDatabase' ) );

		// Set the values to the defaults
		$dbw->update(
			'mw_settings',
			[
				's_settings' => '[]'
			],
			[
				's_dbname' => $this->config->get( 'DBname' )
			],
			__METHOD__
		);

		// Reset the cache or else the changes won't work
		$cWJ = new CreateWikiJson( $this->config->get( 'DBname' ) );
		$cWJ->resetWiki();

		$logEntry = new ManualLogEntry( 'managewiki', 'settings-reset' );
		$logEntry->setPerformer( $this->getContext()->getUser() );
		$logEntry->setTarget( SpecialPage::getTitleValueFor( 'ManageWikiDefaultPermissions' ) );
		$logEntry->setParameters( [ '4::wiki' => $this->config->get( 'DBname' ) ] );
		$logID = $logEntry->insert();
		$logEntry->publish( $logID );

		$out->addHTML(
			Html::successBox(
				Html::element(
					'p',
					[],
					$this->msg( 'managewiki-success' )->plain()
				),
				'mw-notify-success'
			)
			);

		return false;
	}

	public function onSubmitCacheResetForm( $formData ) {
		$out = $this->getOutput();

		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()
			->getMainLB( $this->config->get( 'CreateWikiDatabase' ) )
			->getMaintenanceConnectionRef( DB_PRIMARY, [], $this->config->get( 'CreateWikiDatabase' ) );

		// Reset the cache or else the changes won't work
		$cWJ = new CreateWikiJson( $this->config->get( 'DBname' ) );
		$cWJ->resetWiki();

		$logEntry = new ManualLogEntry( 'managewiki', 'cache-reset' );
		$logEntry->setPerformer( $this->getContext()->getUser() );
		$logEntry->setTarget( SpecialPage::getTitleValueFor( 'ManageWikiDefaultPermissions' ) );
		$logEntry->setParameters( [ '4::wiki' => $this->config->get( 'DBname' ) ] );
		$logID = $logEntry->insert();
		$logEntry->publish( $logID );

		$out->addHTML(
			Html::successBox(
				Html::element(
					'p',
					[],
					$this->msg( 'managewiki-success' )->plain()
				),
				'mw-notify-success'
			)
			);

		return false;
	}


	public static function validateNewGroupName( $newGroup, $nullForm ) {
		if ( in_array( $newGroup, MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'managewiki' )->get( 'ManageWikiPermissionsDisallowedGroups' ) ) ) {
			return 'The group you attempted to create is not allowed. Please select a different name and try again.';
		}

		return true;
	}

	public function buildGroupView( $group ) {
		$out = $this->getOutput();

		$out->addModules( [ 'ext.managewiki.oouiform' ] );
		$out->addModuleStyles( [
			'ext.managewiki.oouiform.styles',
			'mediawiki.widgets.TagMultiselectWidget.styles',
		] );
		$out->addModuleStyles( [ 'oojs-ui-widgets.styles' ] );
		$out->addModules( [ 'mediawiki.special.userrights' ] );

		$remoteWiki = new RemoteWiki( $this->config->get( 'CreateWikiGlobalWiki' ) );

		$formFactory = new ManageWikiFormFactory();
		$htmlForm = $formFactory->getForm( 'default', $remoteWiki, $this->getContext(), $this->config, 'permissions', $group );

		$htmlForm->show();
	}

	public function isListed() {
		$globalwiki = $this->config->get( 'CreateWikiGlobalWiki' );

		// Only appear on the central wiki or if the user can reset permissions on this wiki
		return $globalwiki == $this->config->get( 'DBname' ) || $this->canModify();
	}

	protected function getGroupName() {
		return 'wikimanage';
	}
}
