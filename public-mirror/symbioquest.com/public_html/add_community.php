<?php
// Self-contained migration - add community visibility
$host = 'localhost';
$dbname = 'u890662616_symbio';
$user = 'u890662616_symbio';
$pass = 'Bz8@BKHjQzs';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("ALTER TABLE journals MODIFY COLUMN visibility ENUM('private', 'unlisted', 'community', 'public') DEFAULT 'private'");
    echo "SUCCESS: Added 'community' to visibility ENUM<br>\n";
    
    $stmt = $pdo->prepare("UPDATE journals SET visibility = 'community' WHERE visibility = ''");
    $stmt->execute();
    echo "SUCCESS: Fixed " . $stmt->rowCount() . " journals with empty visibility<br>\n";
    
    echo "<br>DONE - delete this file now";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>\n";
}
