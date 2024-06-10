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

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\RealMe\Constants;
use MediaWiki\Extension\RealMe\Hooks;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Tests\Unit\FakeQqxMessageLocalizer;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\Utils\UrlUtils;

/**
 * @author Taavi Väänänen <hi@taavi.wtf>
 */
class RealMePreferenceValidationTest extends MediaWikiUnitTestCase {
	private function getValidationCallback(): callable {
		$options = [];

		( new Hooks(
			new HashConfig( [ 'RealMeUserPageUrlLimit' => 5 ] ),
			new UrlUtils(),
			$this->createNoOpMock( UserFactory::class ),
			$this->createNoOpMock( UserOptionsLookup::class ),
		) )
			->onGetPreferences(
				$this->createNoOpMock( User::class ),
				$options
			);

		return $options[Constants::PREFERENCE_NAME]['validation-callback'];
	}

	private function getMockForm(): HTMLForm {
		$mockForm = $this->createMock( HTMLForm::class );
		$mockForm->method( 'msg' )->willReturnCallback( [ new FakeQqxMessageLocalizer(), 'msg' ] );
		return $mockForm;
	}

	/**
	 * @param string $value
	 * @dataProvider provideValidPreferences
	 * @covers \MediaWiki\Extension\RealMe\Hooks::onGetPreferences
	 */
	public function testPreferenceValidationValid( string $value ) {
		$callback = $this->getValidationCallback();
		$result = $callback( $value, [], $this->getMockForm() );
		$this->assertTrue( $result );
	}

	public static function provideValidPreferences(): iterable {
		yield 'Empty' => [ '' ];
		yield 'One URL' => [ "https://example.com\n" ];
		yield 'Five URLs' => [
			"https://one.example.com\nhttps://two.example.com\nhttps://three.example.com"
			. "\nhttps://four.example.com\nhttps://five.example.com\n"
		];
		yield 'Empty lines between URLs' => [ "https://example.com\n\nhttps://two.example.com\n" ];
		yield 'Plaintext HTTP works too' => [ "http://example.com\n" ];
	}

	/**
	 * @param string $value
	 * @dataProvider provideInvalidPreferences
	 * @covers \MediaWiki\Extension\RealMe\Hooks::onGetPreferences
	 */
	public function testPreferenceValidationInvalid( string $value ) {
		$callback = $this->getValidationCallback();
		$result = $callback( $value, [], $this->getMockForm() );
		$this->assertIsArray( $result, 'List of errors should be an array' );
	}

	public static function provideInvalidPreferences(): iterable {
		yield 'Not an URL' => [ "bananas\n" ];
		yield 'Six URLs' => [
			"https://one.example.com\nhttps://two.example.com\nhttps://three.example.com"
			. "\nhttps://four.example.com\nhttps://five.example.com\nhttps://six.example.com\n"
		];
		yield 'Mixed URLs and non-urls' => [ "https://one.example.com\nnope\nhttps://six.example.com\n" ];
		yield 'Wrong protocol in URL' => [ "ftp://one.example.com\n" ];
		yield 'Protocol-relative URL' => [ "//one.example.com\n" ];
	}
}
