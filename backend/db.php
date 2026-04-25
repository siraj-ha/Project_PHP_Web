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
    // kol wa7ed yzid table mta3a  code SQL 3adi create table normal bech ki na3mlou run lel code yjou tables fi DB
    

    
    if ($conn->query($users_table) === TRUE) {
   
        $check_admin = "SELECT id FROM users WHERE email = 'admin@gmail.com'";
        $result = $conn->query($check_admin);
        
        if ($result->num_rows == 0) {
            $admin_password = password_hash("adminadmin", PASSWORD_DEFAULT);
            $insert_admin = "INSERT INTO users (full_name, email, password, is_admin) 
                            VALUES ('Administrator', 'admin@gmail.com', '$admin_password', 1)";
            $conn->query($insert_admin);
        }
    }
}
?>