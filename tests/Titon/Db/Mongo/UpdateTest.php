<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Db\Mongo;

use Titon\Db\Entity;
use Titon\Db\Query;
use Titon\Test\Stub\Repository\Stat;
use Titon\Test\Stub\Repository\User;
use Titon\Test\TestCase;
use \MongoId;
use \Exception;

/**
 * Test class for database updating.
 */
class UpdateTest extends TestCase {

    /**
     * Test basic database record updating.
     */
    public function testUpdate() {
        $this->loadFixtures('Users');

        $user = new User();
        $ids = $user->select()->fetchAll();
        $id = $ids[0]['_id'];

        $data = [
            'country_id' => 3,
            'username' => 'milesj'
        ];

        $user->update($id, $data);

        $this->assertEquals(new Entity([
            '_id' => $id,
            'country_id' => 3,
            'username' => 'milesj',
            'password' => '1Z5895jf72yL77h',
            'email' => 'miles@email.com',
            'firstName' => 'Miles',
            'lastName' => 'Johnson',
            'age' => 25,
            'created' => '1988-02-26 21:22:34'
        ]), $user->select()->where('_id', $id)->fetch());
    }

    /**
     * Test database record updating of a record that doesn't exist.
     */
    public function testUpdateNonExistingRecord() {
        $this->loadFixtures('Users');

        $user = new User();
        $data = ['username' => 'foobar'];

        $this->assertEquals(0, $user->update(new MongoId(), $data));
    }

    /**
     * Test updating with empty data.
     */
    public function testUpdateEmptyData() {
        $this->loadFixtures('Users');

        $user = new User();

        try {
            $this->assertEquals(0, $user->update(1, []));
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }

        // Relation without data
        try {
            $user->update(1, [
                'Profile' => [
                    'lastLogin' => time()
                ]
            ]);
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test multiple record updates.
     */
    public function testUpdateMultiple() {
        $this->loadFixtures('Users');

        $user = new User();

        $this->assertSame(4, $user->query(Query::UPDATE)->fields(['country_id' => 1])->where('country_id', '!=', 1)->save());

        $this->assertEquals([
            new Entity(['country_id' => 1, 'username' => 'miles']),
            new Entity(['country_id' => 1, 'username' => 'batman']),
            new Entity(['country_id' => 1, 'username' => 'superman']),
            new Entity(['country_id' => 1, 'username' => 'spiderman']),
            new Entity(['country_id' => 1, 'username' => 'wolverine']),
        ], $user->select('country_id', 'username')->orderBy('_id', 'asc')->fetchAll());

        // No where clause
        $this->assertSame(5, $user->query(Query::UPDATE)->fields(['country_id' => 2])->save());

        $this->assertEquals([
            new Entity(['country_id' => 2, 'username' => 'miles']),
            new Entity(['country_id' => 2, 'username' => 'batman']),
            new Entity(['country_id' => 2, 'username' => 'superman']),
            new Entity(['country_id' => 2, 'username' => 'spiderman']),
            new Entity(['country_id' => 2, 'username' => 'wolverine']),
        ], $user->select('country_id', 'username')->orderBy('_id', 'asc')->fetchAll());
    }

    /**
     * Test multiple record updates with a limit and offset applied.
     */
    public function testUpdateMultipleWithLimit() {
        $this->loadFixtures('Users');

        $user = new User();

        $this->assertSame(1, $user->query(Query::UPDATE)->fields(['country_id' => null])->where('country_id', '!=', 1)->limit(1)->save());

        $this->assertEquals([
            new Entity(['country_id' => 1, 'username' => 'miles']),
            new Entity(['country_id' => null, 'username' => 'batman']),
            new Entity(['country_id' => 2, 'username' => 'superman']),
            new Entity(['country_id' => 5, 'username' => 'spiderman']),
            new Entity(['country_id' => 4, 'username' => 'wolverine']),
        ], $user->select('country_id', 'username')->orderBy('_id', 'asc')->fetchAll());
    }

    /**
     * Test multiple record updates with an order by applied.
     */
    public function testUpdateMultipleWithConditions() {
        $this->loadFixtures('Users');

        $user = new User();

        $this->assertSame(3, $user->query(Query::UPDATE)
            ->fields(['country_id' => null])
            ->where('username', 'like', '/man/')
            ->save());

        $this->assertEquals([
            new Entity(['country_id' => 1, 'username' => 'miles']),
            new Entity(['country_id' => null, 'username' => 'batman']),
            new Entity(['country_id' => null, 'username' => 'superman']),
            new Entity(['country_id' => null, 'username' => 'spiderman']),
            new Entity(['country_id' => 4, 'username' => 'wolverine']),
        ], $user->select('country_id', 'username')->orderBy('_id', 'asc')->fetchAll());
    }

    /**
     * Test multiple record updates setting empty values.
     */
    public function testUpdateMultipleEmptyValue() {
        $this->loadFixtures('Users');

        $user = new User();

        $this->assertSame(5, $user->query(Query::UPDATE)
            ->fields(['firstName' => ''])
            ->save());

        $this->assertEquals([
            new Entity(['username' => 'miles', 'firstName' => '']),
            new Entity(['username' => 'batman', 'firstName' => '']),
            new Entity(['username' => 'superman', 'firstName' => '']),
            new Entity(['username' => 'spiderman', 'firstName' => '']),
            new Entity(['username' => 'wolverine', 'firstName' => '']),
        ], $user->select('username', 'firstName')->orderBy('_id', 'asc')->fetchAll());
    }

    /**
     * Test $inc operator.
     */
    public function testOpInc() {
        $this->loadFixtures('Stats');

        $stat = new Stat();

        $record = $stat->select()->fetch();
        $this->assertEquals(1.0, $record['range']);

        $stat->update($record['_id'], [
            '$inc' => ['range' => 2]
        ]);

        $record = $stat->select()->fetch();
        $this->assertEquals(3.0, $record['range']);
    }

    /**
     * Test $rename operator.
     */
    public function testOpRename() {
        $this->loadFixtures('Stats');

        $stat = new Stat();

        $record = $stat->select()->fetch()->toArray();
        $this->assertArrayHasKey('health', $record);

        $stat->update($record['_id'], [
            '$rename' => ['health' => 'life']
        ]);

        $record = $stat->select()->fetch()->toArray();
        $this->assertArrayNotHasKey('health', $record);
    }

    /**
     * Test $unset operator.
     */
    public function testOpUnset() {
        $this->loadFixtures('Stats');

        $stat = new Stat();

        $record = $stat->select()->fetch()->toArray();
        $this->assertArrayHasKey('health', $record);

        $stat->update($record['_id'], [
            '$unset' => ['health' => '']
        ]);

        $record = $stat->select()->fetch()->toArray();
        $this->assertArrayNotHasKey('health', $record);
    }

    /**
     * Test $set operator.
     */
    public function testOpSet() {
        $this->loadFixtures('Stats');

        $stat = new Stat();

        $record = $stat->select('_id', 'name', 'health', 'range')->fetch();
        $id = $record['_id'];
        unset($record['_id']);

        $this->assertEquals(new Entity([
            'name' => 'Warrior',
            'health' => 1500,
            'range' => 1
        ]), $record);

        $stat->update($id, [
            '$set' => ['health' => 5000, 'range' => 2]
        ]);

        $this->assertEquals(new Entity([
            'name' => 'Warrior',
            'health' => 5000,
            'range' => 2
        ]), $stat->select('name', 'health', 'range')->fetch());
    }

    /**
     * Test all array operators.
     */
    public function testArrayOps() {
        $this->loadFixtures('Stats');

        $stat = new Stat();
        $id = $stat->create([
            'name' => 'Necromancer',
            'health' => 450,
            'energy' => 450,
            'damage' => 0,
            'defense' => 35.75,
            'range' => 1.0,
            'isMelee' => true,
            'spells' => [
                'Reanimate Dead',
                'Corpse Explosion',
                'Summon Zombie'
            ]
        ]);

        // $addToSet
        $stat->update($id, [
            '$addToSet' => [
                'spells' => ['$each' => ['Reanimate Dead', 'Bone Spear']]
            ]
        ]);

        $actual = $stat->read($id);
        $this->assertEquals([
            'Reanimate Dead',
            'Corpse Explosion',
            'Summon Zombie',
            'Bone Spear'
        ], $actual['spells']);

        // $pop
        $stat->update($id, [
            '$pop' => ['spells' => -1]
        ]);

        $actual = $stat->read($id);
        $this->assertEquals([
            'Corpse Explosion',
            'Summon Zombie',
            'Bone Spear'
        ], $actual['spells']);

        // $push
        $stat->update($id, [
            '$push' => [
                'spells' => 'Reanimate Dead'
            ]
        ]);

        $actual = $stat->read($id);
        $this->assertEquals([
            'Corpse Explosion',
            'Summon Zombie',
            'Bone Spear',
            'Reanimate Dead'
        ], $actual['spells']);

        // $pull
        $stat->update($id, [
            '$pull' => [
                'spells' => 'Summon Zombie'
            ]
        ]);

        $actual = $stat->read($id);
        $this->assertEquals([
            'Corpse Explosion',
            'Bone Spear',
            'Reanimate Dead'
        ], $actual['spells']);

        // $pullAll
        $stat->update($id, [
            '$pullAll' => [
                'spells' => ['Corpse Explosion', 'Bone Spear']
            ]
        ]);

        $actual = $stat->read($id);
        $this->assertEquals([
            'Reanimate Dead'
        ], $actual['spells']);
    }

    /**
     * Test _id is auto wrapped with MongoId.
     */
    public function testIdWrap() {
        $this->loadFixtures('Stats');

        $stat = new Stat();

        $record = $stat->select('_id')->fetch();
        $id = $record['_id'];

        // Via object
        $stat->update($id, ['name' => 'Paladin']);
        $this->assertEquals(new Entity(['name' => 'Paladin']), $stat->select('name')->fetch());

        // Via string (should auto wrap)
        $stat->update((string) $id, ['name' => 'Knight']);
        $this->assertEquals(new Entity(['name' => 'Knight']), $stat->select('name')->fetch());
    }

}