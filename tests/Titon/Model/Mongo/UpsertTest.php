<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model\Mongo;

use Titon\Test\Stub\Model\User;
use Titon\Test\TestCase;

/**
 * Test class for database upserting.
 */
class UpsertTest extends TestCase {

    /**
     * Test that the record is created.
     */
    public function testUpsertNoId() {
        $this->loadFixtures('Users');

        $user = new User();

        $this->assertEquals(0, $user->select()->where('username', 'ironman')->count());

        $user->upsert([
            'username' => 'ironman'
        ]);

        $this->assertEquals(1, $user->select()->where('username', 'ironman')->count());
    }

    /**
     * Test that the record is updated if the ID exists in the data.
     */
    public function testUpsertWithId() {
        $this->loadFixtures('Users');

        $user = new User();
        $ids = $user->select()->fetchAll();

        $this->assertEquals(1, $user->select()->where('username', 'miles')->count());

        $user->upsert([
            '_id' => $ids[0]['_id'],
            'username' => 'ironman'
        ]);

        $this->assertEquals(0, $user->select()->where('username', 'miles')->count());
    }

    /**
     * Test that the record is updated if the ID is passed as an argument.
     */
    public function testUpsertWithIdArg() {
        $this->loadFixtures('Users');

        $user = new User();
        $ids = $user->select()->fetchAll();

        $this->assertEquals(1, $user->select()->where('username', 'miles')->count());

        $user->upsert([
            'username' => 'ironman'
        ], $ids[0]['_id']);

        $this->assertEquals(0, $user->select()->where('username', 'miles')->count());
    }

    /**
     * Test that the record is created if the ID doesn't exist.
     */
    public function testUpsertWithFakeId() {
        $this->loadFixtures('Users');

        $user = new User();
        $id = new \MongoId();

        $this->assertFalse($user->exists($id));

        $last_id = $user->upsert([
            '_id' => $id,
            'username' => 'ironman'
        ]);

        $this->assertTrue($user->exists($last_id));
    }

    /**
     * Test that the record is created if the ID argument doesn't exist.
     */
    public function testUpsertWithFakeIdArg() {
        $this->loadFixtures('Users');

        $user = new User();
        $id = new \MongoId();

        $this->assertFalse($user->exists($id));

        $last_id = $user->upsert([
            'username' => 'ironman'
        ], $id);

        $this->assertTrue($user->exists($last_id));
    }

}