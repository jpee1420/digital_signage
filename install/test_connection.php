<?php
/**
 * Database Connection Test Script
 * 
 * This script tests if the MySQL server is available and if the database
 * can be accessed. It's useful for troubleshooting installation issues.
 */

// Display all errors for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Database Connection Test</h1>";

// Test MySQL connection without a specific database
echo "<h2>Testing MySQL Server Connection</h2>";
try {
    $host = isset($_POST['host']) ? $_POST['host'] : 'localhost';
    $user = isset($_POST['user']) ? $_POST['user'] : 'root';
    $pass = isset($_POST['pass']) ? $_POST['pass'] : '';
    
    $serverConn = new mysqli($host, $user, $pass);
    
    if ($serverConn->connect_error) {
        echo "<div style='color: red;'>";
        echo "<p>❌ MySQL Server Connection Failed: " . htmlspecialchars($serverConn->connect_error) . "</p>";
        echo "<p>Please check your database credentials and make sure MySQL server is running.</p>";
        echo "</div>";
    } else {
        echo "<div style='color: green;'>";
        echo "<p>✅ MySQL Server Connection Successful!</p>";
        echo "</div>";
        
        // Get MySQL version
        $version = $serverConn->query("SELECT VERSION() as version")->fetch_assoc();
        echo "<p>MySQL Version: " . $version['version'] . "</p>";
        
        // List available databases
        echo "<h3>Available Databases:</h3>";
        echo "<ul>";
        $result = $serverConn->query("SHOW DATABASES");
        while ($row = $result->fetch_assoc()) {
            echo "<li>" . htmlspecialchars($row['Database']) . "</li>";
        }
        echo "</ul>";
        
        // Test specific database if provided
        if (isset($_POST['db_name']) && !empty($_POST['db_name'])) {
            $db_name = $_POST['db_name'];
            echo "<h2>Testing Connection to Database: " . htmlspecialchars($db_name) . "</h2>";
            
            // Check if database exists
            $dbExists = $serverConn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$db_name'");
            
            if ($dbExists->num_rows == 0) {
                echo "<div style='color: orange;'>";
                echo "<p>⚠️ Database '$db_name' does not exist. It will be created during installation.</p>";
                echo "</div>";
            } else {
                // Try to connect to the specific database
                $dbConn = new mysqli($host, $user, $pass, $db_name);
                
                if ($dbConn->connect_error) {
                    echo "<div style='color: red;'>";
                    echo "<p>❌ Connection to database '$db_name' failed: " . htmlspecialchars($dbConn->connect_error) . "</p>";
                    echo "</div>";
                } else {
                    echo "<div style='color: green;'>";
                    echo "<p>✅ Connection to database '$db_name' successful!</p>";
                    echo "</div>";
                    
                    // Check if tables exist
                    $tables = $dbConn->query("SHOW TABLES");
                    echo "<h3>Tables in database:</h3>";
                    
                    if ($tables->num_rows == 0) {
                        echo "<p>No tables found. The database is empty.</p>";
                    } else {
                        echo "<ul>";
                        while ($row = $tables->fetch_row()) {
                            echo "<li>" . htmlspecialchars($row[0]) . "</li>";
                        }
                        echo "</ul>";
                    }
                    
                    $dbConn->close();
                }
            }
        }
        
        $serverConn->close();
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>

<h2>Test Another Connection</h2>
<form method="post" action="">
    <div style="margin-bottom: 10px;">
        <label for="host">Host:</label>
        <input type="text" name="host" id="host" value="<?php echo isset($_POST['host']) ? htmlspecialchars($_POST['host']) : 'localhost'; ?>">
    </div>
    
    <div style="margin-bottom: 10px;">
        <label for="user">Username:</label>
        <input type="text" name="user" id="user" value="<?php echo isset($_POST['user']) ? htmlspecialchars($_POST['user']) : 'root'; ?>">
    </div>
    
    <div style="margin-bottom: 10px;">
        <label for="pass">Password:</label>
        <input type="password" name="pass" id="pass" value="<?php echo isset($_POST['pass']) ? htmlspecialchars($_POST['pass']) : ''; ?>">
    </div>
    
    <div style="margin-bottom: 10px;">
        <label for="db_name">Database Name (optional):</label>
        <input type="text" name="db_name" id="db_name" value="<?php echo isset($_POST['db_name']) ? htmlspecialchars($_POST['db_name']) : 'smart_schedule'; ?>">
    </div>
    
    <button type="submit">Test Connection</button>
</form>

<p><a href="index.php">Back to Installation Wizard</a></p> 