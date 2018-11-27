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
 * Trait that adds read-only slave connection capability
 *
 * @package    core
 * @category   dml
 * @copyright  2018 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

trait moodle_read_slave_trait {

    /** @var resource master write database handle */
    protected $dbhwrite;

    /** @var resource slave read only database handle */
    protected $dbhreadonly;

    private $readsslave = 0;
    private $slavelatency = 0;

    private $written = array();
    private $readexclude = array();

    /**
     * Gets db handle currently used with queries
     * @return resource
     */
    abstract protected function db_handle();

    /**
     * Sets db handle to be used with subsequent queries
     * @param resource $dbh
     * @return void
     */
    abstract protected function set_db_handle($dbh);

    /**
     * Connect to db
     * Must be called before other methods.
     * @param string $dbhost The database host.
     * @param string $dbuser The database username.
     * @param string $dbpass The database username's password.
     * @param string $dbname The name of the database being connected to.
     * @param mixed $prefix string means moodle db prefix, false used for external databases where prefix not used
     * @param array $dboptions driver specific options
     * @return bool true
     * @throws dml_connection_exception if error
     */
    public function connect($dbhost, $dbuser, $dbpass, $dbname, $prefix, array $dboptions = null) {
        if ($dboptions) {
            if (isset($dboptions['dbhost_readonly'])) {
                $ro = $dboptions['dbhost_readonly'];
                if (is_array($ro)) {
                    /* A random-ish read-only server */
                    switch ($cnt = count($ro)) {
                        case 0:
                            unset($ro);
                            break;
                        case 1:
                            $ro1 = $ro = $ro[0];
                            break;
                        default:
                            $idx = rand(0, $cnt - 1);
                            $ro1 = $ro[$idx];
                    }
                }
            }
            if (isset($ro1)) {
                try {
                    parent::connect($ro1, $dbuser, $dbpass, $dbname, $prefix, $dboptions);
                    $this->dbhreadonly = $this->db_handle();
                    if (isset($dboptions['db_readonly_latency'])) {
                        $this->slavelatency = $dboptions['db_readonly_latency'];
                    }
                    if (isset($dboptions['db_readonly_exclude_tables'])) {
                        $this->readexclude = $dboptions['db_readonly_exclude_tables'];
                        if (!is_array($this->readexclude)) {
                            throw new configuration_exception('db_readonly_exclude_tables must be an array');
                        }
                    }
                } catch (dml_connection_exception $e) {
                    error_log("$e");

                    if (is_array($ro)) {
                        foreach ($ro as $ro2) {
                            if ($ro2 == $ro1) {
                                continue;
                            }
                            try {
                                parent::connect($ro2, $dbuser, $dbpass, $dbname, $prefix, $dboptions);
                                $this->dbhreadonly = $this->db_handle();
                                break;
                            } catch (dml_connection_exception $e) {
                            }
                        }
                    }
                }
            }
        }

        parent::connect($dbhost, $dbuser, $dbpass, $dbname, $prefix, $dboptions);
        $this->dbhwrite = $this->db_handle();
    }

    /**
     * Returns the number of reads done by the read only database.
     * @return int Number of reads.
     */
    public function perf_get_reads_slave() {
        return $this->readsslave;
    }

    /**
     * Called before each db query.
     * @param string $sql
     * @param array $params
     * @param int $type type of query
     * @param mixed $extrainfo driver specific extra information
     * @return void
     */
    protected function query_start($sql, array $params=null, $type, $extrainfo=null) {
        $this->_query_start($sql, $params, $type, $extrainfo);
    }

    protected function _query_start($sql, array $params=null, $type, $extrainfo=null) {
        parent::query_start($sql, $params, $type, $extrainfo);

        if (!$this->dbhreadonly) {
            return;
        }

        if ($this->loggingquery) {
            return;
        }

        // lock_db queries always go to master
        if (preg_match('/lock_db\b/', $sql)) {
            return;
        }

        # Transactions are done as AUX, we cannot play with that
        switch ($type) {
            case SQL_QUERY_SELECT:
                $now = null;
                foreach ($this->table_names($sql) as $t) {
                    if (in_array($t, $this->readexclude)) {
                        break 2;
                    }

                    if ($this->temptables->is_temptable($t)) {
                        break 2;
                    }

                    if (isset($this->written[$t])) {
                        if ($this->slavelatency) {
                            $now = $now ?: microtime(true);
                            if ($now - $this->written[$t] < $this->slavelatency) {
                                break 2;
                            }
                        }
                        else {
                            break 2;
                        }
                    }
                }

                $this->readsslave++;
                $this->set_db_handle($this->dbhreadonly);
                break;
            case SQL_QUERY_INSERT:
            case SQL_QUERY_UPDATE:
            case SQL_QUERY_STRUCTURE:
                $now = $this->slavelatency ? microtime(true) : true;
                foreach ($this->table_names($sql) as $t) {
                    $this->written[$t] = $now;
                }
                break;
        }
    }

    protected function table_names($sql) {
        preg_match_all('/\b'.$this->prefix.'([a-z][A-Za-z0-9_]*)/', $sql, $match);
        return $match[1];
    }

    /**
     * Called immediately after each db query.
     * @param mixed $result db specific
     * @return void
     */
    protected function query_end($result) {
        $this->_query_end($result);
    }

    protected function _query_end($result) {
        if ($this->dbhwrite) { // Sometimes handlers do queries from connect()
            $this->set_db_handle($this->dbhwrite);
        }
        parent::query_end($result);
    }
}
