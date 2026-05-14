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

    $screenings_table = "CREATE TABLE IF NOT EXISTS screenings (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    movie_id INT(11) NOT NULL,
    date DATE NOT NULL,
    time TIME NOT NULL,
    total_seats INT(11) NOT NULL,
    available_seats INT(11) NOT NULL,

    UNIQUE (movie_id, date, time),
    INDEX (movie_id),

    FOREIGN KEY (movie_id) REFERENCES movies(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,

    CHECK (available_seats >= 0 AND available_seats <= total_seats)
    )";
    $conn->query($screenings_table);

    $reservations_table = "CREATE TABLE IF NOT EXISTS reservations (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    screening_id INT(11) NOT NULL,
    seats_reserved INT(11) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status VARCHAR(50) DEFAULT 'confirmed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE (user_id, screening_id),
    INDEX (user_id),
    INDEX (screening_id),
    
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
    
    FOREIGN KEY (screening_id) REFERENCES screenings(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
    )";
    $conn->query($reservations_table);
    
    
 
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