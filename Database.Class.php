<?php

/**
 * Universal Database Class
 *
 * The database class allows connecting to a database using the mysql protocol
 * The main purpose of this class is to insure proper logging of mysql queries
 * Other useful database functions are included and may be added to this class
 *
 * PHP version 5.3
 *
 * @package   Service
 * @author    Original Author Michael Sole <michael.sole@soledevelopment.com>
 * @copyright 2011 Michael Sole
 * @license   http://www.opensource.org/licenses/bsd-license.php The BSD License
 * @version   Release: 2
 */

class Database 
{

    /**
     * Private variable to store SQL results and pass between functions
     * @var resource
     * @access private
     */
    private     $_result;

    /**
     * Description for public
     * @var object
     * @access public
     */
    public      $log;

    /**
     * Link to the instance of the database that this object connects
     * @var resource
     * @access protected
     */
    protected   $link;

    /**
     * Name of database this object is connecting
     * @var string
     * @access public
     */
    public      $database;

    /**
     * Name of server this object is connecting
     * @var string
     * @access public
     */
    public      $server;

    /**
     * Username this object is using to connect
     * @var string
     * @access public
     */
    public      $user;

    /**
     * Password this object is using to connect
     * @var string
     * @access public
     */
    public      $password;

    /**
     * Constructor that creates database connection and sets class properties
     *
     * @param string $server   FDQN host name of database server
     * @param string $user     Username to connect to database
     * @param string $password Password to connect to database
     * @param string $database Database to connect to
     *
     * @return void
     * @access public
     */
    public function __construct($server, $user, $password, $database, $log) {
        $this->log = $log;
        $this->server = $server;
        $this->user = $user;
        $this->password = $password;
        $this->database = $database;
        $this->connectDatabase();
    }

    /**
     * This method creates the actual database connection and sets the link
     * properties
     *
     * @return boolean Returns true if connection is successful
     * @access public
     */
    public function connectDatabase() {
        $this->link = mysql_connect($this->server, $this->user, $this->password);
        if (!$this->link) {
            $this->log->fatal(
                $_SERVER['SCRIPT_FILENAME'].'] CONNECTION-ERROR:'.mysql_error()
            );
            return false;
        } else {
            $db_selected = mysql_select_db($this->database, $this->link);
            if (!$db_selected) {
                $this->log->fatal(
                    $_SERVER['SCRIPT_FILENAME'].'] CONNECTION-ERROR:'.mysql_error()
                );
                return false;
            }
        }
        return true;
    }

    /**
     * Executes a mysql query data should be sanitized
     *
     * @param string  $sql Fully formed and escaped mysql query
     *
     * @return boolean Return results if successful or false if not
     * @access public
     */
    public function runQuery($sql) {

        if (mysql_ping($this->link)) {
            if ($this->_result = mysql_query($sql, $this->link)) {
                $this->log->debug('QUERY:'.$sql);
                return $this->_result;
            } else {
                $this->log->error(
                    'QUERY-ERROR:'.mysql_error().' FAILED QUERY:'.$sql
                );
                return false;
            }
        } else {
            $this->_reconnectDatabase($sql);
        }
        return true;
    }

    /**
     * If connection is lost this we attempt a simple reconnect stratedy
     *
     * @param string  $sql Fully formed mysql query
     *
     * @return boolean Return true if reconnect successful
     * @access private
     */
    private function _reconnectDatabase($sql)
    {
        $this->log->error(
            'DATABASE ERROR: Database went away attempting to reconnect now...'
        );
        $cnt=1;
        while (!mysql_ping($this->link)) {
            $this->log->error('DATABASE ERROR: Reconnection try '.$cnt);
            $cnt++;
            $this->connectDatabase();
            sleep(5);
            if ($cnt==3) {
                $this->log->fatal(
                    'DATABASE ERROR: Reconnection failed halting processing'
                );
                return false;
            }
        }
        $this->runQuery($sql);
    }

    /**
     * Close database connection
     *
     * @return void
     * @access public
     */
    public function closeDatabase()
    {
        mysql_close($this->link);
    }

    /**
     * Utility function to get field names from a mysql result
     *
     * @param resource $results Mysql result resource
     *
     * @return array    Array of field names
     * @access public
     */
    public function getQueryFields($results)
    {
        $fields =Array();
        $num_fields = mysql_num_fields($results);
        for ( $counter = 0; $counter < $num_fields; $counter ++) {
            $fields[] = mysql_field_name($results, $counter);
        }
        return $fields;
    }
}
