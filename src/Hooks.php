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

use MediaWiki\Config\Config;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Content\Content;
use MediaWiki\Content\JsonContent;
use MediaWiki\Context\IContextSource;
use MediaWiki\Hook\EditFilterMergedContentHook;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Json\FormatJson;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\Hook\OutputPageParserOutputHook;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\Utils\UrlUtils;

class Hooks implements
	BeforePageDisplayHook,
	EditFilterMergedContentHook,
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
	public function onBeforePageDisplay( $out, $skin ): void {
		// Add a help link on the JSON config page
		$title = $out->getTitle();
		if ( $title && $title->inNamespace( NS_MEDIAWIKI )
			&& $title->getText() === Constants::CONFIG_PAGE ) {
			$out->addHelpLink( 'Help:Extension:RealMe' );
		}
	}

	/** @inheritDoc */
	public function onEditFilterMergedContent( IContextSource $context, Content $content, Status $status,
		$summary, User $user, $minoredit
	) {
		$title = $context->getTitle();
		if ( !$title || !$title->inNamespace( NS_MEDIAWIKI )
			|| $title->getText() !== Constants::CONFIG_PAGE ) {
			return;
		}

		if ( !$content instanceof JsonContent ) {
			// Huh??
			return;
		}
		if ( !$content->getData()->isGood() ) {
			// Will error out anyways
			return;
		}

		$validator = new Validator( $context, $this->urlUtils );
		$errors = [];
		$data = wfObjectToArray( $content->getData()->getValue() );
		foreach ( $data as $title => $urls ) {
			$errors = array_merge( $errors, $validator->checkTitle( $title ) );
			foreach ( (array)$urls as $url ) {
				$errors = array_merge( $errors, $validator->checkUrl( $url ) );
			}
		}

		if ( $errors ) {
			foreach ( $errors as $error ) {
				$status->fatal( $error );
			}
			return false;
		} else {
			return true;
		}
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
				$urls = explode( "\n", $optionValue ?? '' );

				$errors = [];
				$count = 0;
				$validator = new Validator( $form, $this->urlUtils );

				foreach ( $urls as $url ) {
					if ( trim( $url ) === '' ) {
						continue;
					}

					$count += 1;

					$errors = array_merge( $errors, $validator->checkUrl( $url ) );
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

	/**
	 * Given a Title that corresponds to a User or User talk page, look up the links
	 * that should be added to that page
	 *
	 * @return ?array
	 */
	private function getLinksForUser( Title $title, ParserOutput $parserOutput ) {
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
		$allowedUrls = explode( "\n", $option );

		return array_intersect( $linksPresent, $allowedUrls );
	}

	/**
	 * Given a non-user Title, look up the links that should be added to that page
	 *
	 * @return ?array
	 */
	private function getLinksFromConfig( Title $title ) {
		$config = FormatJson::decode( wfMessage( 'realme-config.json' )->inContentLanguage()->plain(), true );
		if ( !is_array( $config ) ) {
			return;
		}

		$urls = $config[$title->getPrefixedText()] ?? [];
		return (array)$urls;
	}

	/** @inheritDoc */
	public function onOutputPageParserOutput( $outputPage, $parserOutput ): void {
		$title = $outputPage->getTitle();
		if ( !$title ) {
			return;
		}

		if ( !$title->inNamespaces( NS_USER, NS_USER_TALK ) ) {
			// Check sitewide config
			$urls = $this->getLinksFromConfig( $title );
		} else {
			$urls = $this->getLinksForUser( $title, $parserOutput );
		}

		if ( $urls ) {
			foreach ( $urls as $url ) {
				$outputPage->addLink( [ 'rel' => 'me', 'href' => $url ] );
			}
		}
	}
}
