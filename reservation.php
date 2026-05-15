<?php
session_start();
require_once __DIR__ . '/backend/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? '';
$screening_id = isset($_GET['screening_id']) ? (int)$_GET['screening_id'] : 0;
$screening = null;
$error_message = '';
$success_message = '';
$warning_message = '';

// Fetch screening and movie details if screening_id provided
if ($screening_id > 0) {
    $stmt = $conn->prepare('
        SELECT s.id, s.movie_id, s.date, s.time, s.available_seats, s.total_seats, m.title, m.image_url
        FROM screenings s
        JOIN movies m ON s.movie_id = m.id
        WHERE s.id = ? LIMIT 1
    ');
    
    if ($stmt) {
        $stmt->bind_param('i', $screening_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows === 1) {
            $screening = $result->fetch_assoc();
        } else {
            $error_message = 'Screening not found. Please select a valid screening.';
        }
        $stmt->close();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $screening_id_post = isset($_POST['screening_id']) ? (int)$_POST['screening_id'] : 0;
    $phone = trim($_POST['phone'] ?? '');
    $seats_reserved = isset($_POST['seats']) ? (int)$_POST['seats'] : 0;
    
    // Validation
    if ($screening_id_post <= 0) {
        $error_message = 'Invalid screening selected.';
    } elseif ($phone === '') {
        $error_message = 'Please enter your phone number.';
    } elseif ($seats_reserved <= 0) {
        $error_message = 'Please select at least 1 seat.';
    } else {
        // Fetch latest screening data to check available seats
        $checkStmt = $conn->prepare('
            SELECT available_seats, total_seats 
            FROM screenings 
            WHERE id = ? LIMIT 1
        ');
        
        if ($checkStmt) {
            $checkStmt->bind_param('i', $screening_id_post);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult && $checkResult->num_rows === 1) {
              $screeningCheck = $checkResult->fetch_assoc();
              $available = (int) $screeningCheck['available_seats'];

              if ($seats_reserved > $available) {
                $error_message = 'Not enough seats available. Only ' . $available . ' seat(s) remaining.';
              } else {
                $total_price = $seats_reserved * 12.00;
                $status = 'confirmed';

                $conn->begin_transaction();

                $existingReservationStmt = $conn->prepare('SELECT seats_reserved FROM reservations WHERE user_id = ? AND screening_id = ? LIMIT 1');
                if (!$existingReservationStmt) {
                  $conn->rollback();
                  $error_message = 'Could not prepare reservation check. Please try again.';
                } else {
                  $existingReservationStmt->bind_param('ii', $user_id, $screening_id_post);
                  $existingReservationStmt->execute();
                  $existingReservationResult = $existingReservationStmt->get_result();
                  $existingReservation = ($existingReservationResult && $existingReservationResult->num_rows === 1)
                    ? $existingReservationResult->fetch_assoc()
                    : null;
                  $existingReservationStmt->close();
                  $previousSeats = $existingReservation ? (int) $existingReservation['seats_reserved'] : 0;
                  $seatDelta = $seats_reserved - $previousSeats;

                  if ($seatDelta > 0 && $seatDelta > $available) {
                    $conn->rollback();
                    $error_message = 'Not enough seats available. Only ' . $available . ' seat(s) remaining.';
                  } else {
                    if ($existingReservation) {
                      $saveStmt = $conn->prepare('
                        UPDATE reservations
                        SET seats_reserved = ?, total_price = ?, status = ?
                        WHERE user_id = ? AND screening_id = ?
                      ');
                    } else {
                      $saveStmt = $conn->prepare('
                        INSERT INTO reservations (user_id, screening_id, seats_reserved, total_price, status)
                        VALUES (?, ?, ?, ?, ?)
                      ');
                    }

                    if (!$saveStmt) {
                      $conn->rollback();
                      $error_message = 'Could not prepare reservation save request.';
                    } else {
                      if ($existingReservation) {
                        $saveStmt->bind_param('idsii', $seats_reserved, $total_price, $status, $user_id, $screening_id_post);
                      } else {
                        $saveStmt->bind_param('iiids', $user_id, $screening_id_post, $seats_reserved, $total_price, $status);
                      }

                      if ($saveStmt->execute()) {
                        $updateStmt = $conn->prepare('
                          UPDATE screenings
                          SET available_seats = available_seats - ?
                          WHERE id = ?
                        ');

                        if ($updateStmt) {
                          $updateStmt->bind_param('ii', $seatDelta, $screening_id_post);

                          if ($updateStmt->execute()) {
                            $conn->commit();
                            // Store reservation ID for payment page
                            $getReservationIdStmt = $conn->prepare('
                              SELECT id FROM reservations 
                              WHERE user_id = ? AND screening_id = ? 
                              LIMIT 1
                            ');
                            if ($getReservationIdStmt) {
                              $getReservationIdStmt->bind_param('ii', $user_id, $screening_id_post);
                              $getReservationIdStmt->execute();
                              $reservationIdResult = $getReservationIdStmt->get_result();
                              if ($reservationIdResult && $reservationIdResult->num_rows === 1) {
                                $reservationData = $reservationIdResult->fetch_assoc();
                                $_SESSION['reservation_id'] = $reservationData['id'];
                                $_SESSION['reservation_total'] = $total_price;
                                $_SESSION['reservation_seats'] = $seats_reserved;
                              }
                              $getReservationIdStmt->close();
                            }
                            $success_message = $existingReservation
                              ? 'Your reservation has been updated. Total: $' . number_format($total_price, 2)
                              : 'Thank you! Your reservation has been confirmed. Total: $' . number_format($total_price, 2);
                            header('Refresh: 2; url=paiement.php');
                          } else {
                            $conn->rollback();
                            $error_message = 'Failed to update seat availability. Please try again.';
                          }

                          $updateStmt->close();
                        } else {
                          $conn->rollback();
                          $error_message = 'Could not prepare seat availability update.';
                        }
                      } else {
                        $conn->rollback();
                        $error_message = 'Failed to save reservation. Please try again.';
                      }

                      $saveStmt->close();
                    }
                  }
                }
              }
            } else {
                $error_message = 'Screening not found.';
            }
            $checkStmt->close();
        }
    }
}


$screening_date = $screening ? date('M j, Y', strtotime($screening['date'])) : '';
$screening_time = $screening ? date('H:i', strtotime($screening['time'])) : '';

$user_reservations = [];
$reservationsStmt = $conn->prepare('
    SELECT r.id, r.seats_reserved, r.total_price, r.status, r.created_at,
           s.date as screening_date, s.time as screening_time,
           m.title, m.image_url
    FROM reservations r
    JOIN screenings s ON r.screening_id = s.id
    JOIN movies m ON s.movie_id = m.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
');

if ($reservationsStmt) {
    $reservationsStmt->bind_param('i', $user_id);
    $reservationsStmt->execute();
    $reservationsResult = $reservationsStmt->get_result();
    
    if ($reservationsResult) {
        while ($row = $reservationsResult->fetch_assoc()) {
            $user_reservations[] = $row;
        }
    }
    $reservationsStmt->close();
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Reservation - Rengoku.tv</title>

    <!-- Fav Icon -->
    <link rel="shortcut icon" href="icon.ico" type="image/x-icon" />

    <!-- CSS -->
    <link rel="stylesheet" href="css/style.v2.css" />
    <link rel="stylesheet" href="css/swiper-bundle.min.css" />

    <!-- Box Icons -->
    <link
      href="https://cdn.boxicons.com/3.0.3/fonts/basic/boxicons.min.css"
      rel="stylesheet"
    />

    <style>
      .card-container {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 40px;
        align-items: center;
        max-width: 850px;
        margin: 40px auto;
      }

      .card-img {
        position: relative;
        width: 240px;
        aspect-ratio: 2 / 3;
        border-radius: 12px;
        overflow: hidden;
        background: linear-gradient(135deg, #1a1a1a 0%, #0d0d0d 100%);
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.7);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      }

      .card-img::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(to bottom, transparent 60%, rgba(0, 0, 0, 0.3) 100%);
        z-index: 1;
        pointer-events: none;
      }

      .card-img:hover {
        transform: translateY(-10px) scale(1.02);
        box-shadow: 0 30px 70px rgba(0, 0, 0, 0.9);
      }

      .card-img img {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center top;
        transition: transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
      }

      .card-img:hover img {
        transform: scale(1.08);
      }

      .card-content {
        padding: 20px;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
      }

      .card-content h3 {
        font-size: 2rem;
        margin-bottom: 20px;
        color: #fff;
        font-weight: 600;
      }

      .reservation-content {
        width: 100%;
      }

      @media (max-width: 1024px) {
        .card-container {
          grid-template-columns: 280px 1fr;
          gap: 40px;
        }
      }

      @media (max-width: 768px) {
        .card-container {
          grid-template-columns: 1fr;
          gap: 30px;
          justify-items: center;
        }

        .card-img {
          width: 240px;
        }

        .card-content {
          padding: 20px;
        }

        .card-content h3 {
          font-size: 1.6rem;
        }
      }

      @media (max-width: 480px) {
        .card-img {
          width: 200px;
        }
      }

      /* My Reservations Section */
      .my-reservations-section {
        margin-top: 80px;
        padding: 40px 0;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
      }

      .my-reservations-header {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 20px;
        margin-bottom: 40px;
      }

      .my-reservations-section h2 {
        font-size: 2rem;
        color: #fff;
        font-weight: 600;
      }

      .scroll-to-reservations {
        position: relative;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, #e50914 0%, #b20710 100%);
        border: 2px solid rgba(229, 9, 20, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 20px rgba(229, 9, 20, 0.4);
        animation: pulse 2s infinite;
      }

      .scroll-to-reservations:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 30px rgba(229, 9, 20, 0.6);
      }

      .scroll-to-reservations i {
        color: #fff;
        font-size: 1.5rem;
        animation: bounce 1.5s infinite;
      }

      @keyframes pulse {
        0%, 100% {
          box-shadow: 0 4px 20px rgba(229, 9, 20, 0.4);
        }
        50% {
          box-shadow: 0 4px 30px rgba(229, 9, 20, 0.7);
        }
      }

      @keyframes bounce {
        0%, 100% {
          transform: translateY(0);
        }
        50% {
          transform: translateY(4px);
        }
      }

      .reservations-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
        max-width: 1000px;
        margin: 0 auto;
      }

      .reservation-card {
        background: linear-gradient(135deg, #1a1a1a 0%, #0d0d0d 100%);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        border: 1px solid rgba(255, 255, 255, 0.05);
      }

      .reservation-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 50px rgba(0, 0, 0, 0.7);
        border-color: rgba(229, 9, 20, 0.3);
      }

      .reservation-poster {
        position: relative;
        width: 100%;
        aspect-ratio: 2 / 3;
        overflow: hidden;
      }

      .reservation-poster img {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center top;
        transition: transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
      }

      .reservation-card:hover .reservation-poster img {
        transform: scale(1.05);
      }

      .reservation-details {
        padding: 16px;
      }

      .reservation-details h3 {
        font-size: 1rem;
        color: #fff;
        margin-bottom: 12px;
        font-weight: 600;
        line-height: 1.4;
      }

      .reservation-info {
        display: flex;
        flex-direction: column;
        gap: 8px;
      }

      .reservation-info-item {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #999;
        font-size: 0.85rem;
      }

      .reservation-info-item i {
        color: #e50914;
        font-size: 1rem;
      }

      .reservation-info-item span {
        color: #ccc;
      }

      .reservation-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 14px;
        padding-top: 12px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
      }

      .reservation-price {
        font-size: 0.95rem;
        color: #e50914;
        font-weight: 600;
      }

      .reservation-status {
        padding: 4px 10px;
        border-radius: 16px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }

      .reservation-status.confirmed {
        background: rgba(40, 167, 69, 0.2);
        color: #28a745;
        border: 1px solid rgba(40, 167, 69, 0.3);
      }

      .reservation-status.cancelled {
        background: rgba(220, 53, 69, 0.2);
        color: #dc3545;
        border: 1px solid rgba(220, 53, 69, 0.3);
      }

      .empty-reservations {
        text-align: center;
        padding: 60px 20px;
        background: linear-gradient(135deg, #1a1a1a 0%, #0d0d0d 100%);
        border-radius: 16px;
        border: 1px solid rgba(255, 255, 255, 0.05);
      }

      .empty-reservations i {
        font-size: 4rem;
        color: #333;
        margin-bottom: 20px;
      }

      .empty-reservations p {
        color: #999;
        font-size: 1.1rem;
        margin-bottom: 20px;
      }

      .empty-reservations a {
        display: inline-block;
        padding: 12px 30px;
        background: #e50914;
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
      }

      .empty-reservations a:hover {
        background: #f40612;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(229, 9, 20, 0.4);
      }

      @media (max-width: 1024px) {
        .reservations-grid {
          grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
          gap: 25px;
        }
      }

      @media (max-width: 768px) {
        .my-reservations-section {
          margin-top: 60px;
          padding: 30px 0;
        }

        .my-reservations-section h2 {
          font-size: 1.6rem;
          margin-bottom: 30px;
        }

        .reservations-grid {
          grid-template-columns: 1fr;
          gap: 20px;
        }

        .reservation-details {
          padding: 20px;
        }

        .reservation-details h3 {
          font-size: 1.1rem;
        }
      }

      @media (max-width: 480px) {
        .my-reservations-section h2 {
          font-size: 1.4rem;
        }

        .reservation-info-item {
          font-size: 0.85rem;
        }

        .reservation-price {
          font-size: 1rem;
        }
      }
    </style>
  </head>

  <body>
    <!-- Header -->
    <header>
      <div class="nav container">
        <a href="main.php" class="logo">Rengoku<span>.tv</span></a>

        <ul class="nav-menu">
          <li><a href="main.php#movies" class="nav-link">Movies</a></li>
          <li><a href="main.php#trending" class="nav-link">Coming Soon</a></li>
          <li>
            <a href="reservation.php" class="nav-link active">Reservations</a>
          </li>
          <li><a href="contact.html" class="nav-link">Contact</a></li>
          <li class="user-menu">
            <input type="checkbox" id="user-toggle" title="User menu toggle" />
            <label for="user-toggle" class="user-icon">
              <i class="bx bx-user-circle"></i>
              <i class="bx bxs-chevron-down"></i>
            </label>

            <ul class="dropdown-menu">
              <li><a href="profile.php" class="login-btn">Profile</a></li>
              <li><a href="logout.php" class="signup-btn">Logout</a></li>
            </ul>
          </li>
        </ul>
      </div>
    </header>

    <!-- Reservation Section -->
    <section class="banner container">
      <div class="reservation-content">
        <h2>TAKE YOUR TICKETS NOW!</h2>

        <?php if ($success_message !== ''): ?>
          <div style="background-color: #28a745; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
            <strong><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></strong><br>
            <small>Redirecting to home page...</small>
          </div>
        <?php endif; ?>

        <?php if ($warning_message !== ''): ?>
          <div style="background-color: #ffc107; color: #111; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <strong>Warning:</strong> <?php echo htmlspecialchars($warning_message, ENT_QUOTES, 'UTF-8'); ?>
          </div>
        <?php endif; ?>

        <?php if ($error_message !== ''): ?>
          <div style="background-color: #dc3545; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <strong>Error:</strong> <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
          </div>
        <?php endif; ?>

        <?php if (!$screening && !$success_message): ?>
          <div style="background-color: #ffc107; color: #000; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 20px;">
            <p>No screening selected. Please select a screening from a movie page.</p>
            <a href="main.php" class="btn" style="display: inline-block; margin-top: 10px; padding: 10px 20px; background: #e50914; color: white; text-decoration: none; border-radius: 5px;">Back to Movies</a>
          </div>
        <?php elseif ($screening && !$success_message): ?>
          <div class="card-container">
            <div class="card-img">
              <?php if (!empty($screening['image_url'])): ?>
                <img 
                  src="<?php echo htmlspecialchars($screening['image_url'], ENT_QUOTES, 'UTF-8'); ?>" 
                  alt="<?php echo htmlspecialchars($screening['title'], ENT_QUOTES, 'UTF-8'); ?>"
                  loading="lazy"
                  onerror="this.src='img/aa.PNG'"
                />
              <?php else: ?>
                <img 
                  src="img/aa.PNG" 
                  alt="No image available"
                />
              <?php endif; ?>
            </div>

            <div class="card-content">
              <h3>Book a Seat</h3>
              <p style="color: #999; margin-bottom: 15px; font-size: 0.9rem;">Movie: <strong><?php echo htmlspecialchars($screening['title'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
              
              <form method="POST" action="reservation.php">
                <input type="hidden" name="screening_id" value="<?php echo (int)$screening_id; ?>" />

                <div class="form-row">
                  <div class="form-group">
                    <i class="bx bx-calendar"></i>
                    <input type="text" placeholder="Date" value="<?php echo htmlspecialchars($screening_date, ENT_QUOTES, 'UTF-8'); ?>" readonly disabled />
                  </div>

                  <div class="form-group">
                    <i class="bx bx-time-five"></i>
                    <input type="text" placeholder="Time" value="<?php echo htmlspecialchars($screening_time, ENT_QUOTES, 'UTF-8'); ?>" readonly disabled />
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group">
                    <i class="bx bx-user"></i>
                    <input type="text" placeholder="Full Name" value="<?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?>" readonly disabled />
                  </div>
                  <div class="form-group">
                    <i class="bx bx-phone"></i>
                    <input type="text" name="phone" placeholder="Phone Number" required />
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group">
                    <i class="bx bx-group"></i>
                    <input
                      type="number"
                      name="seats"
                      placeholder="How Many Persons?"
                      min="1"
                      max="<?php echo (int)$screening['available_seats']; ?>"
                      required
                    />
                    <small style="color: #999; display: block; margin-top: 5px; font-size: 0.85rem;">Available: <?php echo (int)$screening['available_seats']; ?>/<?php echo (int)$screening['total_seats']; ?> seats</small>
                  </div>
                </div>

                <input type="submit" value="TAKE TICKETS" class="btn" />
              </form>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- My Reservations Section -->
    <section class="banner container">
      <div class="my-reservations-section">
        <div class="my-reservations-header">
          <h2>My Reservations</h2>
          <?php if (!empty($user_reservations)): ?>
            <div class="scroll-to-reservations" onclick="scrollToReservations()">
              <i class="bx bx-chevron-down"></i>
            </div>
          <?php endif; ?>
        </div>

        <?php if (empty($user_reservations)): ?>
          <div class="empty-reservations">
            <i class="bx bx-ticket"></i>
            <p>You haven't made any reservations yet.</p>
            <a href="main.php#movies">Browse Movies</a>
          </div>
        <?php else: ?>
          <div class="reservations-grid" id="reservations-grid">
            <?php foreach ($user_reservations as $reservation): ?>
              <div class="reservation-card">
                <div class="reservation-poster">
                  <?php if (!empty($reservation['image_url'])): ?>
                    <img
                      src="<?php echo htmlspecialchars($reservation['image_url'], ENT_QUOTES, 'UTF-8'); ?>"
                      alt="<?php echo htmlspecialchars($reservation['title'], ENT_QUOTES, 'UTF-8'); ?>"
                      loading="lazy"
                      onerror="this.src='img/aa.PNG'"
                    />
                  <?php else: ?>
                    <img
                      src="img/aa.PNG"
                      alt="No image available"
                    />
                  <?php endif; ?>
                </div>

                <div class="reservation-details">
                  <h3><?php echo htmlspecialchars($reservation['title'], ENT_QUOTES, 'UTF-8'); ?></h3>

                  <div class="reservation-info">
                    <div class="reservation-info-item">
                      <i class="bx bx-calendar"></i>
                      <span><?php echo date('M j, Y', strtotime($reservation['screening_date'])); ?></span>
                    </div>
                    <div class="reservation-info-item">
                      <i class="bx bx-time-five"></i>
                      <span><?php echo date('H:i', strtotime($reservation['screening_time'])); ?></span>
                    </div>
                    <div class="reservation-info-item">
                      <i class="bx bx-user"></i>
                      <span><?php echo (int)$reservation['seats_reserved']; ?> seat(s)</span>
                    </div>
                  </div>

                  <div class="reservation-footer">
                    <span class="reservation-price">$<?php echo number_format($reservation['total_price'], 2); ?></span>
                    <span class="reservation-status <?php echo htmlspecialchars(strtolower($reservation['status']), ENT_QUOTES, 'UTF-8'); ?>">
                      <?php echo htmlspecialchars(ucfirst($reservation['status']), ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- Scripts -->
    <script src="js/swiper-bundle.min.js"></script>
    <script src="js/main.js"></script>
    <script>
      function escapeHtml(text) {
        const map = {
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#039;",
        };

        return String(text).replace(/[&<>"']/g, function (char) {
          return map[char];
        });
      }

      const dropdownMenu = document.querySelector(".dropdown-menu");

      if (dropdownMenu) {
        // User is already logged in at this point (PHP checked it)
        dropdownMenu.innerHTML = `
          <li><a href="profile.php" class="login-btn">Profile</a></li>
          <li><a href="logout.php" class="signup-btn">Logout</a></li>
        `;
      }

      // Close user menu when clicking outside
      document.addEventListener("click", function (event) {
        const userMenu = document.querySelector(".user-menu");
        const userToggle = document.querySelector("#user-toggle");

        if (userMenu && !userMenu.contains(event.target)) {
          if (userToggle) userToggle.checked = false;
        }
      });

      // Scroll to reservations grid
      function scrollToReservations() {
        const reservationsGrid = document.getElementById('reservations-grid');
        if (reservationsGrid) {
          reservationsGrid.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }
    </script>
  </body>
</html>
