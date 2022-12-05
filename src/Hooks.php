<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * @file
 */

namespace MediaWiki\Extension\RealMe;

use MediaWiki\Hook\OutputPageParserOutputHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserOptionsLookup;

class Hooks implements
	GetPreferencesHook,
	OutputPageParserOutputHook
{
	/** @var UserFactory */
	private UserFactory $userFactory;

	/** @var UserOptionsLookup */
	private UserOptionsLookup $userOptionsLookup;

	/**
	 * @param UserFactory $userFactory
	 * @param UserOptionsLookup $userOptionsLookup
	 */
	public function __construct(
		UserFactory $userFactory,
		UserOptionsLookup $userOptionsLookup
	) {
		$this->userFactory = $userFactory;
		$this->userOptionsLookup = $userOptionsLookup;
	}

	/** @inheritDoc */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences[Constants::PREFERENCE_NAME] = [
			'type'          => 'textarea',
			'section'       => 'personal/userpage',
			'label-message' => 'realme-preference-desc',
			'help-message'  => 'realme-preference-help',
			'rows'          => 5,
		];
	}

	/** @inheritDoc */
	public function onOutputPageParserOutput( $outputPage, $parserOutput ): void {
		$title = $outputPage->getTitle();
		if ( !$title ) {
			return;
		}

		if ( $title->getNamespace() !== NS_USER ) {
			return;
		}

		if ( $title->isSubpage() ) {
			return;
		}

		$user = $this->userFactory->newFromName( $title->getText() );

		if ( !$user ) {
			return;
		}

		$linksPresent = array_keys( $parserOutput->getExternalLinks() );

		if ( !$linksPresent ) {
			return;
		}

		$option = $this->userOptionsLookup->getOption( $user, Constants::PREFERENCE_NAME, "" );
		$allowedUrls = explode( PHP_EOL, $option );

		foreach ( array_intersect( $linksPresent, $allowedUrls ) as $url ) {
			$outputPage->addLink( [ 'href' => $url, 'rel' => 'me' ] );
		}
	}
}
