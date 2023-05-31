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

use MediaWiki\Title\Title;
use MediaWiki\Utils\UrlUtils;
use MessageLocalizer;

class Validator {

	/** @var MessageLocalizer */
	private $localizer;

	/** @var UrlUtils */
	private $urlUtils;

	public function __construct( MessageLocalizer $localizer, UrlUtils $urlUtils ) {
		$this->localizer = $localizer;
		$this->urlUtils = $urlUtils;
	}

	/**
	 * Validate the specified URL, returning errors if necessary
	 */
	public function checkUrl( string $url ): array {
		$errors = [];
		$parsed = $this->urlUtils->parse( $url );
		if ( !$parsed ) {
			$errors[] = $this->localizer->msg( 'realme-preference-error-invalid' )
				->plaintextParams( $url );
			return $errors;
		}

		if ( $parsed['scheme'] !== 'http' && $parsed['scheme'] !== 'https' ) {
			$errors[] = $this->localizer->msg( 'realme-preference-error-not-http' )
				->plaintextParams( $url, $parsed['scheme'] . $parsed['delimiter'] );
		}

		return $errors;
	}

	/**
	 * Validate the specified title, returning errors if necessary
	 */
	public function checkTitle( string $title ): array {
		$errors = [];
		$titleObj = Title::newFromText( $title );
		if ( $titleObj ) {
			if ( $titleObj->getPrefixedText() !== $title ) {
				$errors[] = $this->localizer->msg( 'realme-config-error-canonical' )
					->plaintextParams( $titleObj->getPrefixedText(), $title );
			}
		} else {
			$errors[] = $this->localizer->msg( 'realme-config-error-invalidtitle' )
				->plaintextParams( $title );
		}

		return $errors;
	}
}
