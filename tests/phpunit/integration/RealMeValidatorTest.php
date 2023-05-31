<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

use MediaWiki\Extension\RealMe\Validator;
use MediaWiki\Tests\Unit\FakeQqxMessageLocalizer;
use MediaWiki\Utils\UrlUtils;

/** @covers \MediaWiki\Extension\RealMe\Validator */
class RealMeValidatorTest extends MediaWikiIntegrationTestCase {

	/** @var Validator */
	private $validator;

	public function setUp(): void {
		parent::setUp();
		$this->validator = new Validator( new FakeQqxMessageLocalizer(), new UrlUtils() );
	}

	private function assertHasError( $errors, $key ) {
		$errors = array_map( static function ( $message ) {
			return $message->getKey();
		}, $errors );
		$this->assertContains( $key, $errors );
	}

	/**
	 * @dataProvider provideCheckUrl
	 */
	public function testCheckUrl( string $value, $expected ) {
		$errors = $this->validator->checkUrl( $value );
		$this->assertIsArray( $errors );
		if ( $expected !== null ) {
			$this->assertHasError( $errors, $expected );
		}
	}

	public static function provideCheckUrl() {
		yield [ '', 'realme-preference-error-invalid' ];
		yield [ 'nyaa', 'realme-preference-error-invalid' ];
		yield [ 'nyaa.example', 'realme-preference-error-invalid' ];
		yield [ 'ftp://nyaa.example', 'realme-preference-error-not-http' ];
		yield [ 'https://nyaa.example', null ];
	}

	/**
	 * @dataProvider provideCheckTitle
	 */
	public function testCheckTitle( string $value, $expected ) {
		$errors = $this->validator->checkTitle( $value );
		$this->assertIsArray( $errors );
		if ( $expected !== null ) {
			$this->assertHasError( $errors, $expected );
		}
	}

	public static function provideCheckTitle() {
		yield [ '', 'realme-config-error-invalidtitle' ];
		yield [ '<<nyaa>>', 'realme-config-error-invalidtitle' ];
		yield [ 'nyaa', 'realme-config-error-canonical' ];
		yield [ 'Nyaa', null ];
	}
}
