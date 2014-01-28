<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Db\Mongo;

use Titon\Common\Config;
use Titon\Db\Query;
use Titon\Db\Query\Expr;
use Titon\Db\Query\Predicate;
use Titon\Test\Stub\Repository\User;
use Titon\Test\TestCase;
use \MongoRegex;

/**
 * Test class for dialect SQL building.
 *
 * @property \Titon\Db\Mongo\MongoDialect $object
 */
class DialectTest extends TestCase {

    /**
     * @type \Titon\Db\Driver
     */
    public $driver;

    /**
     * This method is called before a test is executed.
     */
    protected function setUp() {
        $this->driver = new MongoDriver('default', Config::get('db'));
        $this->driver->connect();

        $this->object = $this->driver->getDialect();
    }

    /**
     * Close the connection.
     */
    protected function tearDown() {
        parent::tearDown();

        $this->driver->disconnect();
    }

    /**
     * Test expression building.
     */
    public function testFormatExpression() {
        $expr = new Expr('id', '=', 1);
        $this->assertEquals(['id' => 1], $this->object->formatExpression($expr));

        $expr = new Expr('id', '>=', 1);
        $this->assertEquals(['id' => ['$gte' => 1]], $this->object->formatExpression($expr));

        $expr = new Expr('id', '!=', 1);
        $this->assertEquals(['id' => ['$ne' => 1]], $this->object->formatExpression($expr));

        $expr = new Expr('id', 'in', [1]);
        $this->assertEquals(['id' => ['$in' => [1]]], $this->object->formatExpression($expr));

        $expr = new Expr('id', 'like', '/foo/i');
        $this->assertEquals(['id' => new MongoRegex('/foo/i')], $this->object->formatExpression($expr));

        $expr = new Expr('id', 'notLike', '/bar/i');
        $this->assertEquals(['id' => ['$not' => new MongoRegex('/bar/i')]], $this->object->formatExpression($expr));

        $regex = new MongoRegex('/bar/i');
        $expr = new Expr('id', 'notLike', $regex);
        $this->assertEquals(['id' => ['$not' => $regex]], $this->object->formatExpression($expr));

        $expr = new Expr('id', 'between', [1, 3]);
        $this->assertEquals(['id' => ['$gte' => 1, '$lte' => 3]], $this->object->formatExpression($expr));

        $expr = new Expr('id', 'notBetween', [1, 3]);
        $this->assertEquals(['$or' => [['id' => ['$lt' => 1]], ['id' => ['$gt' => 3]]]], $this->object->formatExpression($expr));

        // Direct operator usage
        $expr = new Expr('id', '$in', [1, 3]);
        $this->assertEquals(['id' => ['$in' => [1, 3]]], $this->object->formatExpression($expr));
    }

    /**
     * Test fields are formatted to Mongo syntax.
     */
    public function testFormatFields() {
        $query = new Query(Query::CREATE_INDEX, new User());
        $query->fields([
            'one',
            'two' => 'asc',
            'three' => -1
        ]);
        $this->assertEquals([
            'one' => 1,
            'two' => 1,
            'three' => -1
        ], $this->object->formatFields($query));

        // Select
        $query = new Query(Query::SELECT, new User());
        $query->fields('id', 'status', 'username');
        $this->assertEquals(['_id' => 1, 'status' => 1, 'username' => 1], $this->object->formatFields($query));

        $query->fields('status', 'username');
        $this->assertEquals(['_id' => 0, 'status' => 1, 'username' => 1], $this->object->formatFields($query));

        // Update
        $query = new Query(Query::UPDATE, new User());
        $fields = ['username' => 'miles', '$inc' => ['post_count' => 1]];

        $query->fields($fields);
        $this->assertEquals([
            '$set' => ['username' => 'miles'],
            '$inc' => ['post_count' => 1]
        ], $this->object->formatFields($query));

        $fields['$set'] = ['status' => 2];
        $query->fields($fields);
        $this->assertEquals([
            '$set' => ['username' => 'miles', 'status' => 2],
            '$inc' => ['post_count' => 1]
        ], $this->object->formatFields($query));
    }

    /**
     * Test order by directions.
     */
    public function testFormatOrderBy() {
        $this->assertEquals([
            'one' => 1,
            'two' => 1,
            'three' => -1
        ], $this->object->formatOrderBy([
            'one',
            'two' => 'asc',
            'three' => -1
        ]));
    }

    /**
     * Test predicate formatting.
     */
    public function testFormatPredicate() {
        $pred = new Predicate(Predicate::ALSO);
        $pred->eq('id', 1)->gte('age', 12);

        $this->assertEquals([
            'id' => 1,
            'age' => ['$gte' => 12]
        ], $this->object->formatPredicate($pred));

        // Include on same field
        $pred->lte('age', 20)->in('color', ['red', 'green', 'blue']);

        $this->assertEquals([
            'id' => 1,
            'age' => ['$gte' => 12, '$lte' => 20],
            'color' => ['$in' => ['red', 'green', 'blue']]
        ], $this->object->formatPredicate($pred));

        // Subgroup
        $pred->either(function() {
            $this->like('name', '/Titon/i')->notLike('name', '/Symfony/i');
            $this->also(function() {
                $this->eq('active', 1)->notEq('status', 2);
            });
        });

        $this->assertEquals([
            'id' => 1,
            'age' => ['$gte' => 12, '$lte' => 20],
            'color' => ['$in' => ['red', 'green', 'blue']],
            '$or' => [
                ['name' => new MongoRegex('/Titon/i')],
                ['name' => ['$not' => new MongoRegex('/Symfony/i')]],
                [
                    '$and' => [
                        ['active' => 1],
                        ['status' => ['$ne' => 2]]
                    ]
                ]
            ]
        ], $this->object->formatPredicate($pred));

        // Or needs to be wrapped
        $pred = new Predicate(Predicate::EITHER);
        $pred->eq('id', 1)->gte('age', 12);

        $this->assertEquals([
            '$or' => [
                ['id' => 1],
                ['age' => ['$gte' => 12]]
            ]
        ], $this->object->formatPredicate($pred));
    }

}