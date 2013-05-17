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

    /**
     * Add a message to the queue
     *
     * @param string  $phone   Valid 10 digit phone
     * @param string  $message The body of the message
     * @param string  $cid     Category that you are sending the message
     * @param string  $title   Message title (subject for email)
     * @param string  $stamp   Mysql date time
     * @param string  $type    SMS, Email, Audio or Text
     * @param string  $email   Email address
     * @param string  $sid     Subscriber ID
     *
     * @return boolean Returns true if successful
     * @access public
     */
    public function addMessageToQueue(
        $phone, $message, $cid, $title, $stamp, $type = 'sms', $email='', $sid='', $debug=false
    ) {
        if ($type == 'sms' && empty($phone)) {
            return false;
        } else if ($type == 'email' && empty($email)) {
            return false;
        } else {
            $sql = "INSERT INTO bm_message_queue
                    (stamp, phone, email, message, cid, title, type, sid)
                    VALUES
                    ('".$stamp."',
                    '".$phone."',
                    '".$email."',
                    '".$message."',
                    '".$cid."',
                    '".$title."',
                    '".$type."',
                    '".$sid."');";
            
            if ($debug)
            {
                echo $sql;
            }

            return ($this->runQuery($sql)) ? true : false;
        }
    }

    /**
     * Returns destination type based on message type so:
     * email for email, mobile for sms and voice phone for audio/text
     *
     * @param unknown $sid  Subscriber ID
     * @param unknown $type Message type
     *
     * @return string  Returns destination field
     * @access private
     */
    private function _getSubscriberContactByType($sid, $type)
    {
        // Init
        $column = '';

        switch ($type)
        {
        case 'sms':
            $column = 'mobile';
            break;

        case 'email':
            $column = 'email';
            break;

        case 'audio':
        case 'text':
        case 'voice':
            $column = 'voice_phone';
            break;
        }

        $sql = sprintf(
            'SELECT %s AS destination
            FROM bm_subscribers WHERE id=%d',
            $column,
            $sid
        );
        $result = $this->runQuery($sql);
        while ($row = mysql_fetch_array($result)) {
            $destination = $row['destination'];
        }

        return $destination;
    }

    /**
     * Gets type of blast being sent
     *
     * @param string  $mid Message ID
     *
     * @return string  Returns blast type
     * @access private
     */
    private function _getBlastMessageType($mid)
    {
        $type = '';
        $sql = 'SELECT type FROM bm_message WHERE id = ' . $mid;
        $result = $this->runQuery($sql);

        while ($row = mysql_fetch_array($result)) {
            $type = $row['type'];
        }

        return $type;
    }

    // Send message blasts


    /**
     * This function converts scheduled message into messages in a queue
     * ready to be sent
     *
     * @param string  $mid         Message ID
     * @param string  $whereClause Specific where close to append to query
     *
     * @return boolean Return true if successful
     * @access public
     */
    public function sendMessageBlast($mid, $whereClause = '')
    {

        $type = $this->_getBlastMessageType($mid);

        switch ($type)
        {
        case 'f_sms':
        case 'sms':
            $col    = 's.mobile';
            $phone  = 's.mobile';
            break;
        case 'f_email':
        case 'email':
            $col    = 's.email';
            $phone  = 's.mobile';
            break;
        case 'f_voice':
        case 'voice':
        case 'audio':
        case 'text':
        case 'f_audio':
        case 'f_text':
            $col    = 's.voice_phone';
            $phone  = 's.voice_phone';
            break;
        }

        switch ($type)
        {
        case 'f_sms':
        case 'f_email':
        case 'f_voice':
        case 'f_audio':
        case 'f_text':
            // Build where clause
            $whereClause = '';
            $sql = '
                SELECT query
                FROM bm_filters
                WHERE mid = ' . $mid . '
                LIMIT 1
            ';
            $result = $this->runQuery($sql);
            while ($row = mysql_fetch_array($result)) {
                $whereClause = stripslashes($row['query']) . ' AND ';
            }
            break;
        }

        $sql = 'INSERT INTO bm_message_queue
                (phone, email, message, cid, mid, type, title, sid,status)
                SELECT ' . $phone . ' AS phone,
                    s.email AS email,
                    m.message AS message,
                    c.cid AS cid,
                    m.id AS mid,

                    CASE m.type
                    WHEN "f_sms" THEN "sms"
                    WHEN "sms" THEN "sms"
                    WHEN "f_email" THEN "email"
                    WHEN "email" THEN "email"
                    WHEN "f_audio" THEN "audio"
                    WHEN "audio" THEN "audio"
                    WHEN "f_text" THEN "text"
                    WHEN "text" THEN "text"
                    END AS type,

                    m.title AS title,
                    s.id AS sid,
                    "Started"
                FROM bm_subscribers s
                    LEFT JOIN bm_category_sub c ON s.id=c.sid
                    LEFT JOIN bm_category_send cs ON cs.cid=c.cid
                    LEFT JOIN bm_message m ON m.id=cs.mid
                WHERE
                    ' . $whereClause . '
                    m.id = ' . $mid . ' AND
                    c.optin = 1
                GROUP BY ' . $col;
        $result = $this->runQuery($sql);

        return ( $result ) ? true : false;
    }

    // Send individual messages to message queue


    /**
     * This is similiar to addMessageToQueue but newer and should replace older
     * in future release
     *
     * @param string  $stamp   Mysql date time
     * @param string  $message Actual message to be sent
     * @param string  $title   Message title (subject for email)
     * @param string  $sid     Subscriber ID
     * @param string  $type    SMS, Email, Audio or Text
     * @param string  $cid     Category that you are sending the message
     *
     * @return boolean Return true if successful
     * @access public
     */
    public function SendMessage($stamp, $message, $title, $sid, $type, $cid=null)
    {
        // Init
        $phone  = '';
        $email  = '';

        // Get the subscriber information based on the message type
        // sms = mobile
        // email = email address
        // voice = voice phone
        switch ($type)
        {
        case 'sms':
            $phone = $this->_getSubscriberContactByType($sid, $type);
            break;

        case 'email':
            $email = $this->_getSubscriberContactByType($sid, $type);
            break;

        case 'audio':
        case 'text':
        case 'voice':
            $phone = $this->_getSubscriberContactByType($sid, $type);
            break;
        }

        $sql = sprintf(
            'INSERT INTO bm_message_queue
            stamp, message, cid, title, sid, type, phone, email)
            VALUES ("%s", "%s", %d, "%s", %d, "%s", "%s", "%s")',
            $stamp,
            mysql_real_escape_string($message),
            $cid,
            mysql_real_escape_string($title),
            $sid,
            $type,
            $phone,
            $email
        );
        $result = $this->runQuery($sql);

        return ( $result ) ? true : false;
    }
}
