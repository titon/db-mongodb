<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Db\Mongo;

use Titon\Db\Entity;
use Titon\Db\EntityCollection;
use Titon\Db\Query;
use Titon\Test\Stub\Repository\Book;
use Titon\Test\Stub\Repository\Genre;
use Titon\Test\Stub\Repository\Stat;
use Titon\Test\Stub\Repository\User;
use Titon\Test\TestCase;
use \Exception;
use \MongoCode;

/**
 * Test class for database reading.
 */
class ReadTest extends TestCase {

    /**
     * Test basic fetching of rows.
     */
    public function testFirst() {
        $this->loadFixtures('Books');

        $book = new Book();

        // Single
        $this->assertEquals(new Entity([
            'series_id' => 1,
            'name' => 'A Game of Thrones',
            'isbn' => '0-553-10354-7',
            'released' => '1996-08-02'
        ]), $book->select('series_id', 'name', 'isbn', 'released')->first());

        // Multiple
        $this->assertEquals(new EntityCollection([
            new Entity([
                'series_id' => 3,
                'name' => 'The Fellowship of the Ring',
                'isbn' => '',
                'released' => '1954-07-24'
            ]),
            new Entity([
                'series_id' => 3,
                'name' => 'The Two Towers',
                'isbn' => '',
                'released' => '1954-11-11'
            ]),
            new Entity([
                'series_id' => 3,
                'name' => 'The Return of the King',
                'isbn' => '',
                'released' => '1955-10-25'
            ]),
        ]), $book->select('series_id', 'name', 'isbn', 'released')->where('series_id', 3)->orderBy('_id', 'asc')->all());
    }

    /**
     * Test row counting.
     */
    public function testSelectCount() {
        $this->loadFixtures('Books');

        $book = new Book();

        $query = $book->select();
        $this->assertEquals(15, $query->count());

        $query->where('series_id', 2);
        $this->assertEquals(7, $query->count());

        $query->where('name', 'like', '/prince/i');
        $this->assertEquals(1, $query->count());
    }

    /**
     * Test LIKE and NOT LIKE clauses.
     */
    public function testSelectLike() {
        $this->loadFixtures('Users');

        $user = new User();

        $this->assertEquals(new EntityCollection([
            new Entity(['username' => 'batman']),
            new Entity(['username' => 'superman']),
            new Entity(['username' => 'spiderman']),
        ]), $user->select('username')->where('username', 'like', '/man/')->orderBy('_id', 'asc')->all());

        $this->assertEquals(new EntityCollection([
            new Entity(['username' => 'miles']),
            new Entity(['username' => 'wolverine'])
        ]), $user->select('username')->where('username', 'notLike', '/man/')->orderBy('_id', 'asc')->all());
    }

    /**
     * Test REGEXP and NOT REGEXP clauses.
     */
    public function testSelectRegexp() {
        $this->loadFixtures('Users');

        $user = new User();

        $this->assertEquals(new EntityCollection([
            new Entity(['username' => 'batman']),
            new Entity(['username' => 'superman']),
            new Entity(['username' => 'spiderman']),
        ]), $user->select('username')->where('username', 'regexp', '/man$/')->orderBy('id', 'asc')->all());

        $this->assertEquals(new EntityCollection([
            new Entity(['username' => 'miles']),
            new Entity(['username' => 'wolverine'])
        ]), $user->select('username')->where('username', 'notRegexp', '/man$/')->all());
    }

    /**
     * Test IN and NOT IN clauses.
     */
    public function testSelectIn() {
        $this->loadFixtures('Users');

        $user = new User();

        $this->assertEquals(new EntityCollection([
            new Entity(['username' => 'miles']),
            new Entity(['username' => 'superman']),
        ]), $user->select('username')->where('username', 'in', ['miles', 'superman'])->all()); // use fake 10

        $this->assertEquals(new EntityCollection([
            new Entity(['username' => 'batman']),
            new Entity(['username' => 'spiderman']),
            new Entity(['username' => 'wolverine'])
        ]), $user->select('username')->where('username', 'notIn', ['miles', 'superman'])->all());
    }

    /**
     * Test BETWEEN and NOT BETWEEN clauses.
     */
    public function testSelectBetween() {
        $this->loadFixtures('Users');

        $user = new User();

        $this->assertEquals(new EntityCollection([
            new Entity(['username' => 'batman']),
            new Entity(['username' => 'superman']),
        ]), $user->select('username')->where('age', 'between', [30, 45])->all());

        $this->assertEquals(new EntityCollection([
            new Entity(['username' => 'miles']),
            new Entity(['username' => 'spiderman']),
            new Entity(['username' => 'wolverine'])
        ]), $user->select('username')->where('age', 'notBetween', [30, 45])->all());
    }

    /**
     * Test IS NULL and NOT NULL clauses.
     */
    public function testSelectNull() {
        $this->loadFixtures('Users');

        $user = new User();
        $user->query(Query::UPDATE)->fields(['created' => null])->where('country_id', 1)->save();

        $this->assertEquals(new EntityCollection([
            new Entity(['username' => 'miles'])
        ]), $user->select('username')->where('created', 'isNull', null)->all());

        $this->assertEquals(new EntityCollection([
            new Entity(['username' => 'batman']),
            new Entity(['username' => 'superman']),
            new Entity(['username' => 'spiderman']),
            new Entity(['username' => 'wolverine'])
        ]), $user->select('username')->where('created', 'isNotNull', null)->orderBy('_id', 'asc')->all());
    }

    /**
     * Test field filtering.
     */
    public function testFieldFiltering() {
        $this->loadFixtures('Books');

        $book = new Book();

        $this->assertEquals(new EntityCollection([
            new Entity(['name' => 'A Game of Thrones']),
            new Entity(['name' => 'A Clash of Kings']),
            new Entity(['name' => 'A Storm of Swords']),
            new Entity(['name' => 'A Feast for Crows']),
            new Entity(['name' => 'A Dance with Dragons']),
        ]), $book->select('name')->where('series_id', 1)->orderBy('_id', 'asc')->all());
    }

    /**
     * Test group by clause.
     */
    public function testGrouping() {
        $this->loadFixtures('Books');

        $book = new Book();

        $this->assertEquals(3, count($book->select('name')->groupBy('series_id')->orderBy('id', 'asc')->all()));
    }

    /**
     * Test limit and offset.
     */
    public function testLimiting() {
        $this->loadFixtures('Genres');

        $genre = new Genre();

        // Limit only
        $this->assertEquals(new EntityCollection([
            new Entity(['name' => 'Action']),
            new Entity(['name' => 'Adventure']),
            new Entity(['name' => 'Action-Adventure'])
        ]), $genre->select('name')->limit(3)->all());

        // Limit and offset
        $this->assertEquals(new EntityCollection([
            new Entity(['name' => 'Comedy']),
            new Entity(['name' => 'Horror']),
            new Entity(['name' => 'Thriller'])
        ]), $genre->select('name')->limit(3, 3)->all());
    }

    /**
     * Test order by clause.
     */
    public function testOrdering() {
        $this->loadFixtures('Books');

        $book = new Book();

        $this->assertEquals(new EntityCollection([
            new Entity(['series_id' => 3, 'name' => 'The Return of the King']),
            new Entity(['series_id' => 3, 'name' => 'The Two Towers']),
            new Entity(['series_id' => 3, 'name' => 'The Fellowship of the Ring']),
            new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Deathly Hallows']),
            new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Half-blood Prince']),
            new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Order of the Phoenix']),
            new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Goblet of Fire']),
            new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Prisoner of Azkaban']),
            new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Chamber of Secrets']),
            new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Philosopher\'s Stone']),
            new Entity(['series_id' => 1, 'name' => 'A Dance with Dragons']),
            new Entity(['series_id' => 1, 'name' => 'A Feast for Crows']),
            new Entity(['series_id' => 1, 'name' => 'A Storm of Swords']),
            new Entity(['series_id' => 1, 'name' => 'A Clash of Kings']),
            new Entity(['series_id' => 1, 'name' => 'A Game of Thrones']),
        ]), $book->select('series_id', 'name')->orderBy([
            'series_id' => 'desc',
            '_id' => 'desc'
        ])->all());

        $this->assertEquals(new EntityCollection([
            new Entity(['series_id' => 3, 'name' => 'The Fellowship of the Ring']),
            new Entity(['series_id' => 3, 'name' => 'The Return of the King']),
            new Entity(['series_id' => 3, 'name' => 'The Two Towers']),
            new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Chamber of Secrets']),
            new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Deathly Hallows']),
            new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Goblet of Fire']),
            new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Half-blood Prince']),
            new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Order of the Phoenix']),
            new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Philosopher\'s Stone']),
            new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Prisoner of Azkaban']),
            new Entity(['series_id' => 1, 'name' => 'A Clash of Kings']),
            new Entity(['series_id' => 1, 'name' => 'A Dance with Dragons']),
            new Entity(['series_id' => 1, 'name' => 'A Feast for Crows']),
            new Entity(['series_id' => 1, 'name' => 'A Game of Thrones']),
            new Entity(['series_id' => 1, 'name' => 'A Storm of Swords']),
        ]), $book->select('series_id', 'name')->orderBy([
            'series_id' => 'desc',
            'name' => 'asc'
        ])->all());
    }

    /**
     * Test where predicates using AND conjunction.
     */
    public function testWhereAnd() {
        $this->loadFixtures('Stats');

        $stat = new Stat();

        $this->assertEquals(new EntityCollection([
            new Entity([
                'name' => 'Ranger',
                'health' => 800,
                'isMelee' => false
            ])
        ]), $stat->select('name', 'health', 'isMelee')
            ->where('isMelee', false)
            ->where('health', '>=', 700)
            ->all());

        $this->assertEquals(new EntityCollection([
            new Entity([
                'name' => 'Ranger',
                'health' => 800,
                'energy' => 335,
                'range' => 6.75
            ]),
            new Entity([
                'name' => 'Mage',
                'health' => 600,
                'energy' => 600,
                'range' => 8.33
            ])
        ]), $stat->select('name', 'health', 'energy', 'range')
            ->where('health', '<', 1000)
            ->where('range', '>=', 5)
            ->where('energy', '!=', 0)
            ->all());

        $this->assertEquals(new EntityCollection([
            new Entity([
                'name' => 'Warrior',
                'health' => 1500,
                'isMelee' => true,
                'range' => 1
            ])
        ]), $stat->select('name', 'health', 'isMelee', 'range')
            ->where(function(Query\Predicate $where) {
                $where->gte('health', 500)->lte('range', 7)->eq('isMelee', true);
            })->all());
    }

    /**
     * Test where predicates using OR conjunction.
     */
    public function testWhereOr() {
        $this->loadFixtures('Stats');

        $stat = new Stat();

        $this->assertEquals(new EntityCollection([
            new Entity([
                'name' => 'Warrior',
                'health' => 1500,
                'range' => 1
            ]),
            new Entity([
                'name' => 'Mage',
                'health' => 600,
                'range' => 8.33
            ])
        ]), $stat->select('name', 'health', 'range')
            ->orWhere('health', '>', 1000)
            ->orWhere('range', '>', 7)
            ->all());

        $this->assertEquals(new EntityCollection([
            new Entity([
                'name' => 'Warrior',
                'damage' => 125.25,
                'defense' => 55.75,
                'range' => 1
            ]),
            new Entity([
                'name' => 'Ranger',
                'damage' => 90.45,
                'defense' => 30.5,
                'range' => 6.75
            ]),
            new Entity([
                'name' => 'Mage',
                'damage' => 55.84,
                'defense' => 40.15,
                'range' => 8.33
            ])
        ]), $stat->select('name', 'damage', 'defense', 'range')
            ->orWhere(function(Query\Predicate $where) {
                $where->gt('damage', 100)->gt('range', 5)->gt('defense', 50);
            })
            ->all());
    }

    /**
     * Test nested where predicates.
     */
    public function testWhereNested() {
        $this->loadFixtures('Stats');

        $stat = new Stat();

        $this->assertEquals(new EntityCollection([
            new Entity(['name' => 'Mage'])
        ]), $stat->select('name')
            ->where(function(Query\Predicate $where) {
                $where->eq('isMelee', false);
                $where->either(function(Query\Predicate $where2) {
                    $where2->lte('health', 600)->lte('damage', 60);
                });
            })->all());
    }

    /**
     * Test = and $ne operator.
     */
    public function testOpEq() {
        $this->loadFixtures('Stats');

        $stat = new Stat();

        $this->assertEquals(new EntityCollection([
            new Entity(['name' => 'Warrior'])
        ]), $stat->select('name')->where('range', 1.0)->all());

        $this->assertEquals(new EntityCollection([
            new Entity(['name' => 'Ranger']),
            new Entity(['name' => 'Mage']),
        ]), $stat->select('name')->where('range', '!=', 1.0)->all());
    }

    /**
     * Test $all operator.
     */
    public function testOpAll() {
        $this->loadFixtures('Stats');
    }

    /**
     * Test $gt and $gte operator.
     */
    public function testOpGt() {
        $this->loadFixtures('Stats');

        $stat = new Stat();

        $this->assertEquals(new EntityCollection([
            new Entity(['name' => 'Warrior'])
        ]), $stat->select('name')->where('health', '>', 800)->all());

        $this->assertEquals(new EntityCollection([
            new Entity(['name' => 'Warrior']),
            new Entity(['name' => 'Ranger']),
        ]), $stat->select('name')->where('health', '>=', 800)->all());
    }

    /**
     * Test $lt and $lte operator.
     */
    public function testOpLt() {
        $this->loadFixtures('Stats');

        $stat = new Stat();

        $this->assertEquals(new EntityCollection([
            new Entity(['name' => 'Mage'])
        ]), $stat->select('name')->where('health', '<', 800)->all());

        $this->assertEquals(new EntityCollection([
            new Entity(['name' => 'Ranger']),
            new Entity(['name' => 'Mage']),
        ]), $stat->select('name')->where('health', '<=', 800)->all());
    }

    /**
     * Test $in and $nin operator.
     */
    public function testOpIn() {
        $this->loadFixtures('Stats');

        $stat = new Stat();

        $this->assertEquals(new EntityCollection([
            new Entity(['name' => 'Warrior'])
        ]), $stat->select('name')->where('range', 'in', [1, 2, 3])->all());

        $this->assertEquals(new EntityCollection([
            new Entity(['name' => 'Ranger']),
            new Entity(['name' => 'Mage']),
        ]), $stat->select('name')->where('range', 'notIn', [1, 2, 3])->all());
    }

    /**
     * Test $exists operator.
     */
    public function testOpExists() {
        $this->loadFixtures('Stats');

        $stat = new Stat();
        $stat->create(['name' => 'Necromancer']);

        $this->assertEquals(new EntityCollection([
            new Entity(['name' => 'Warrior']),
            new Entity(['name' => 'Ranger']),
            new Entity(['name' => 'Mage']),
        ]), $stat->select('name')->where('range', 'exists', true)->all());

        $this->assertEquals(new EntityCollection([
            new Entity(['name' => 'Necromancer']),
        ]), $stat->select('name')->where('range', 'exists', false)->all());
    }

    /**
     * Test $type and $not operator.
     */
    public function testOpTypeAndNot() {
        $this->loadFixtures('Stats');

        $stat = new Stat();
        $stat->create(['name' => 'Necromancer', 'isMelee' => 'N/A']);

        $this->assertEquals(new EntityCollection([
            new Entity(['name' => 'Warrior']),
            new Entity(['name' => 'Ranger']),
            new Entity(['name' => 'Mage']),
        ]), $stat->select('name')->where('isMelee', 'type', MongoDialect::TYPE_BOOLEAN)->all());

        $this->assertEquals(new EntityCollection([
            new Entity(['name' => 'Necromancer']),
        ]), $stat->select('name')->where('isMelee', 'not', ['$type' => MongoDialect::TYPE_BOOLEAN])->all());
    }

    /**
     * Test $exists operator.
     */
    public function testOpWhere() {
        $this->loadFixtures('Stats');

        $stat = new Stat();

        $this->assertEquals(new EntityCollection([
            new Entity(['name' => 'Warrior'])
        ]), $stat->select('name')->where('isMelee', 'where', 'function() { return (this.isMelee == true); }')->all());

        $this->assertEquals(new EntityCollection([
            new Entity(['name' => 'Ranger']),
            new Entity(['name' => 'Mage']),
        ]), $stat->select('name')->where('damage', 'where', new MongoCode('function() { return (this.damage > min && this.damage < max); }', ['min' => 50, 'max' => 100]))->all());

        // Not MongoCode
        try {
            $this->assertEquals(new EntityCollection([
                new Entity(['name' => 'Warrior'])
            ]), $stat->select('name')->where('isMelee', 'where', 123456)->all());

            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
    }

}