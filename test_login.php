<?php
// Simple script to test the DB connection and forcefully reset the 'testuser' password.
// Access this via http://localhost/AayuVault/test_login.php (or your server port)

define('DB_HOST',   'localhost');
define('DB_NAME',   'clinic_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET','utf8mb4');

echo "<h2>AayuVault Authentication Tester</h2>";

try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "<p style='color:green;'>✅ Database connection successful!</p>";
    
    // Check if testuser exists
    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = "testuser"');
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user) {
        echo "<p>✅ User 'testuser' found in database.</p>";
        
        // Let's force reset the password to 'password123'
        $newPlainPassword = 'password123';
        $newHash = password_hash($newPlainPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        
        $upd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE username = "testuser"');
        $upd->execute([$newHash]);
        
        echo "<p style='color:green;'>✅ Password for 'testuser' has been successfully reset.</p>";
        echo "<p><strong>Now try logging in at the regular login page with:</strong><br>";
        echo "Username: <b>testuser</b><br>Password: <b>password123</b></p>";
    } else {
        echo "<p style='color:red;'>❌ 'testuser' not found. It seems the database hasn't been seeded properly with the clinic_db_schema.sql file.</p>";
    }

} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Make sure XAMPP MySQL is running and you have imported clinic_db_schema.sql into phpMyAdmin.</p>";
}
?>
