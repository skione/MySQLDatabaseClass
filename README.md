MySQLDatabaseClass
==================

This is a simple database class to work with the basic MySQL PHP package.

It is designed to work with the simple logging class that I also wrote.

To instantiate the class pass it server, username, password, database and log object.

$db = new Database($server, $username, $password, $database, $log);

The you can run queries like this:

$sql = 'SELECT * FROM table';

$db-runQuery($sql);

There is no sanitization of input so you should use mqsyl_real_escape string on data going in and strip slashes 
on data coming out. I'll probably upgrade this at some point.

The code is designed to be resiliant to db connection failures so if mysql is not available it will try a few times
to re-connect.
