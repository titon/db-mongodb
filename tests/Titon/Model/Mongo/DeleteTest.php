<?php
/**
 * @copyright	Copyright 2010-2013, The Titon Project
 * @license		http://opensource.org/licenses/bsd-license.php
 * @link		http://titon.io
 */

namespace Titon\Model\Mongo;

use Titon\Model\Query;
use Titon\Test\Stub\Model\User;
use Titon\Test\TestCase;

/**
 * Test class for database record deleting.
 */
class DeleteTest extends TestCase {

	/**
	 * Test delete with where conditions.
	 */
	public function testDeleteConditions() {
		$this->loadFixtures('Users');

		$user = new User();

		$this->assertSame(5, $user->select()->count());
		$this->assertSame(3, $user->query(Query::DELETE)->where('age', '>', 30)->save());
		$this->assertSame(2, $user->select()->count());
	}

	/**
	 * Test delete using justOnce option.
	 */
	public function testDeleteJustOnce() {
		$this->loadFixtures('Users');

		$user = new User();

		$this->assertSame(5, $user->select()->count());
		$this->assertSame(1, $user->query(Query::DELETE)->limit(1)->save());
		$this->assertSame(4, $user->select()->count());
	}

}