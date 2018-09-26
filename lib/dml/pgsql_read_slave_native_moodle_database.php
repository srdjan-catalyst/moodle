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
 * Native pgsql class derrivative with read-only slave database connection support
 *
 * @package    core
 * @category   dml
 * @copyright  2018 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/pgsql_native_moodle_database.php');
require_once(__DIR__.'/moodle_read_slave_trait.php');

/**
 * Native pgsql class derrivative with read-only slave database connection support
 *
 * @package    core
 * @category   dml
 * @copyright  2018 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pgsql_read_slave_native_moodle_database extends pgsql_native_moodle_database {
    use moodle_read_slave_trait;

    /**
     * Returns more specific database driver type
     * Note: can be used before connect()
     * @return string db type
     */
    protected function get_dbtype() {
        return 'pgsql_read_slave';
    }

    /**
     * Gets db handle currently used with queries
     * @return resource
     */
    protected function db_handle() {
        return $this->pgsql;
    }

    /**
     * Sets db handle to be used with subsequent queries
     * @param resource $dbh
     * @return void
     */
    protected function set_db_handle($dbh) {
        $this->pgsql = $dbh;
    }
}
