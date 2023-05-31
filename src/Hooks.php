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

use Config;
use HTMLForm;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Hook\OutputPageParserOutputHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserOptionsLookup;
use MediaWiki\Utils\UrlUtils;

class Hooks implements
	GetPreferencesHook,
	OutputPageParserOutputHook
{
	private const CONSTRUCTOR_OPTIONS = [
		'RealMeUserPageUrlLimit',
	];

	/** @var ServiceOptions */
	private ServiceOptions $options;

	/** @var UrlUtils */
	private UrlUtils $urlUtils;

	/** @var UserFactory */
	private UserFactory $userFactory;

	/** @var UserOptionsLookup */
	private UserOptionsLookup $userOptionsLookup;

	/**
	 * @param Config $mainConfig
	 * @param UrlUtils $urlUtils
	 * @param UserFactory $userFactory
	 * @param UserOptionsLookup $userOptionsLookup
	 */
	public function __construct(
		Config $mainConfig,
		UrlUtils $urlUtils,
		UserFactory $userFactory,
		UserOptionsLookup $userOptionsLookup
	) {
		$this->options = new ServiceOptions( self::CONSTRUCTOR_OPTIONS, $mainConfig );
		$this->urlUtils = $urlUtils;
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
			'rows'          => min( 5, $this->options->get( 'RealMeUserPageUrlLimit' ) ),

			'validation-callback' => function ( $optionValue, $allData, HTMLForm $form ) {
				$urls = explode( PHP_EOL, $optionValue ?? '' );

				$errors = [];
				$count = 0;

				foreach ( $urls as $url ) {
					if ( trim( $url ) === '' ) {
						continue;
					}

					$count += 1;

					$parsed = $this->urlUtils->parse( $url );
					if ( !$parsed ) {
						$errors[] = $form->msg( 'realme-preference-error-invalid' )
							->plaintextParams( $url );
						continue;
					}

					if ( $parsed['scheme'] !== 'http' && $parsed['scheme'] !== 'https' ) {
						$errors[] = $form->msg( 'realme-preference-error-not-http' )
							->plaintextParams( $url, $parsed['scheme'] . $parsed['delimiter'] );
					}
				}

				if ( $count > $this->options->get( 'RealMeUserPageUrlLimit' ) ) {
					$errors[] = $form->msg( 'realme-preference-error-too-many' )
						->params( $this->options->get( 'RealMeUserPageUrlLimit' ) );
				}

				if ( $errors ) {
					return $errors;
				}

				return true;
			},
		];
	}

	/** @inheritDoc */
	public function onOutputPageParserOutput( $outputPage, $parserOutput ): void {
		$title = $outputPage->getTitle();
		if ( !$title ) {
			return;
		}

		if ( !$title->inNamespaces( NS_USER, NS_USER_TALK ) ) {
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
