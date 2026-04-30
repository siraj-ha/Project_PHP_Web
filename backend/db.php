<?php
$servername = "localhost";
$username = "root"; 
$password = ""; 
$dbname = "cinema_db";

$conn = new mysqli($servername, $username, $password);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    $conn->select_db($dbname);
    
  
    $users_table = "CREATE TABLE IF NOT EXISTS users (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        phone VARCHAR(20),
        password VARCHAR(255) NOT NULL,
        is_admin TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($users_table);
  
    $movies_table = "CREATE TABLE IF NOT EXISTS movies (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        description TEXT,
        image_url VARCHAR(500),
        status VARCHAR(50) DEFAULT 'available',
        trailer_url VARCHAR(500),
        genre VARCHAR(100),
        rating DECIMAL(2,1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($movies_table);
    
 
    $check_admin = "SELECT id FROM users WHERE email = 'admin@gmail.com'";
    $result = $conn->query($check_admin);
    
    if ($result->num_rows == 0) {
        $admin_password = password_hash("adminadmin", PASSWORD_DEFAULT);
        $insert_admin = "INSERT INTO users (full_name, email, password, is_admin) 
                        VALUES ('Administrator', 'admin@gmail.com', '$admin_password', 1)";
        $conn->query($insert_admin);
    }
}
?>