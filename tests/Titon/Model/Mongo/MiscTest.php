<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model\Mongo;

use MongoBinData;
use MongoDate;
use Titon\Model\Query;
use Titon\Test\Stub\Model\Mongo;
use Titon\Test\Stub\Model\User;
use Titon\Test\TestCase;

/**
 * Test class for misc database functionality.
 */
class MiscTest extends TestCase {

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

    /**
     * Test type casting.
     */
    public function testTypeCasting() {
        $mongo = new Mongo();

        // Create a record with every type
        $id = $mongo->create([
            'int' => 123456,
            'int32' => 123456,
            'int64' => 123456,
            'string' => 'abc',
            'array' => ['foo', 'bar'],
            'object' => ['foo' => 'bar'],
            'float' => 12.34,
            'double' => 123.45,
            'datetime' => time(),
            'blob' => 'Binary data!'
        ]);

        $this->assertEquals([
            '_id' => $id,
            'int' => 123456,
            'int32' => '123456',
            'int64' => '123456',
            'string' => 'abc',
            'array' => ['foo', 'bar'],
            'object' => ['foo' => 'bar'],
            'float' => 12.34,
            'double' => 123.45,
            'datetime' => new MongoDate(time()),
            'blob' => new MongoBinData('Binary data!', 2)
        ], $mongo->select()->where('_id', $id)->fetch(false));

        // Test defaults and nulls
        $id = $mongo->create([
            'array' => null
        ]);

        $this->assertEquals([
            '_id' => $id,
            'array' => null,
            'datetime' => null
        ], $mongo->select()->where('_id', $id)->fetch(false));

        $mongo->query(Query::DROP_TABLE)->save();
    }

    /**
     * Test that fetch list works.
     */
    public function testFetchList() {
        $mongo = new Mongo();

        $mongo->create(['name' => 'PHP']);
        $mongo->create(['name' => 'RoR']);
        $mongo->create(['name' => 'Java']);
        $mongo->create(['name' => 'Python']);

        $this->assertEquals(['PHP', 'RoR', 'Java', 'Python'], array_values($mongo->select()->fetchList('_id', 'name')));

        $mongo->query(Query::DROP_TABLE)->save();
    }

}