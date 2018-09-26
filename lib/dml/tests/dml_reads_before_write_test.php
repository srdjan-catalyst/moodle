<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * DML reads before write recording tests.
 *
 * @package    core
 * @category   dml
 * @copyright  2018 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * DML reads before write recording tests.
 *
 * @package    core
 * @category   dml
 * @copyright  2018 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_dml_reads_before_write_testcase extends base_testcase {
    /** @var string test table name used in these tests*/
    protected static $tablename = 'test_table';

    /**
     * Constructs a test case with the given name.
     *
     * @param string $name
     * @param array  $data
     * @param string $dataname
     */
    final public function __construct($name = null, array $data = array(), $dataname = '') {
        parent::__construct($name, $data, $dataname);

        $this->setBackupGlobals(false);
        $this->setBackupStaticAttributes(false);
        $this->setRunTestInSeparateProcess(false);
    }

    /**
     * Instantiates a database interface object
     *
     * @return moodle_database $db
     */
    private static function db_conn() {
        global $CFG;

        if (defined('PHPUNIT_TEST_DRIVER')) {
            if (!isset($CFG->phpunit_extra_drivers[PHPUNIT_TEST_DRIVER])) {
                throw new exception('Can not find driver configuration options with index: '.PHPUNIT_TEST_DRIVER);
            }
            $cfg = $CFG->phpunit_extra_drivers[PHPUNIT_TEST_DRIVER];
        } else {
            $cfg = (array)$CFG;
        }

        $dblibrary = empty($cfg['dblibrary']) ? 'native' : $cfg['dblibrary'];
        $dbtype = $cfg['dbtype'];
        $dbhost = $cfg['dbhost'];
        $dbname = $cfg['dbname'];
        $dbuser = $cfg['dbuser'];
        $dbpass = $cfg['dbpass'];
        $prefix = $cfg['prefix'];
        $dboptions = isset($cfg['dboptions']) ? $cfg['dboptions'] : array();

        $classname = "{$dbtype}_{$dblibrary}_moodle_database";
        require_once("$CFG->libdir/dml/$classname.php");
        $d = new $classname();
        if (!$d->driver_installed()) {
            throw new exception('Database driver for '.$classname.' is not installed');
        }

        $d->connect($dbhost, $dbuser, $dbpass, $dbname, $prefix, $dboptions);
        return $d;
    }

    public static function setUpBeforeClass() {
        parent::setUpBeforeClass();

        $dbman = self::db_conn()->get_manager();

        $table = new xmldb_table(self::$tablename);
        $table->setComment("This is a test'n drop table. You can drop it safely");
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $dbman->create_table($table);
    }

    public static function tearDownAfterClass() {
        $dbman = self::db_conn()->get_manager();

        $table = new xmldb_table(self::$tablename);
        $dbman->drop_table($table);

        phpunit_util::reset_all_data(null);

        parent::tearDownAfterClass();
    }

    public function setUp() {
        parent::SetUp();
        $this->tdb = self::db_conn();
    }

    public function tearDown() {
        $this->tdb->dispose();
        $this->tdb = null;
        parent::tearDown();
    }

    public function test_reads() {
        $DB = self::db_conn();

        $this->assertEquals(0, $DB->perf_get_reads_before_write());
        $DB->get_records(self::$tablename);
        $readsbeforewrite = $DB->perf_get_reads_before_write();
        $this->assertGreaterThan(0, $readsbeforewrite);

        $DB->insert_record_raw(self::$tablename, array('name' => 'blah'));
        $DB->get_records(self::$tablename);

        $this->assertEquals($readsbeforewrite, $DB->perf_get_reads_before_write());
    }
}
