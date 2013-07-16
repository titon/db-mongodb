<?php
/**
 * @copyright	Copyright 2010-2013, The Titon Project
 * @license		http://opensource.org/licenses/bsd-license.php
 * @link		http://titon.io
 */

namespace Titon\Model\Mongo;

use Titon\Test\Stub\Model\User;
use Titon\Test\TestCase;

/**
 * Test class for misc database functionality.
 */
class MiscTest extends TestCase {

	/**
	 * Unload fixtures.
	 */
	protected function tearDown() {
		parent::tearDown();

		$this->unloadFixtures();
	}

	/**
	 * Test table truncation.
	 */
	public function testTruncateTable() {
		$this->loadFixtures('Users');

		$user = new User();

		$this->assertEquals(5, $user->select()->count());

		$user->query(Query::TRUNCATE)->save();

		$this->assertEquals(0, $user->select()->count());
	}


}