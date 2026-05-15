<?php
session_start();
require_once __DIR__ . '/backend/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}


$reservation_id = $_SESSION['reservation_id'] ?? 0;
$total_amount = $_SESSION['reservation_total'] ?? 0;
$seats_reserved = $_SESSION['reservation_seats'] ?? 0;
$user_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? '';

$message = '';
$reservation_details = null;
$payment_success = false;


if ($reservation_id > 0) {
    $stmt = $conn->prepare('
        SELECT r.id, r.total_price, r.seats_reserved, s.date, s.time, m.title, m.image_url
        FROM reservations r
        JOIN screenings s ON r.screening_id = s.id
        JOIN movies m ON s.movie_id = m.id
        WHERE r.id = ? AND r.user_id = ? LIMIT 1
    ');
    
    if ($stmt) {
        $stmt->bind_param('ii', $reservation_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows === 1) {
            $reservation_details = $result->fetch_assoc();
            $total_amount = (float)$reservation_details['total_price'];
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = trim($_POST['payment_method'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    
    if ($reservation_id <= 0) {
        $message = "❌ No reservation found. Please complete a reservation first.";
    } elseif ($amount <= 0) {
        $message = "❌ Invalid payment amount.";
    } elseif ($payment_method === '') {
        $message = "❌ Please select a payment method.";
    } else {
        $sql = "INSERT INTO payments
                (reservation_id, payment_method, amount, payment_status)
                VALUES (?, ?, ?, 'paid')";

        $payStmt = $conn->prepare($sql);

        if ($payStmt) {
            $payStmt->bind_param("isd", $reservation_id, $payment_method, $amount);

            if ($payStmt->execute()) {
                $message = "✅ Payment successful! Redirecting to dashboard...";
                $payment_success = true;
                header('Refresh: 2; url=main.php');
            } else {
                $message = "❌ Payment failed. Please try again.";
            }

            $payStmt->close();
        } else {
            $message = "❌ Database error. Please try again.";
        }
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payment - Rengoku.tv</title>

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
      }

      .card-img img {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center top;
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

      .reservation-summary {
        background: rgba(229, 9, 20, 0.1);
        border: 1px solid rgba(229, 9, 20, 0.3);
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        color: #ccc;
      }

      .reservation-summary h4 {
        color: #fff;
        margin-bottom: 10px;
      }

      .reservation-summary p {
        margin: 5px 0;
        font-size: 0.9rem;
      }

      .total-amount {
        font-size: 1.3rem;
        color: #e50914;
        font-weight: 600;
        margin-top: 10px;
      }

      @media (max-width: 768px) {
        .card-container {
          grid-template-columns: 1fr;
          gap: 30px;
          justify-items: center;
        }

        .card-img {
          width: 200px;
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

    <!-- PAYMENT SECTION -->
    <section class="banner container">
      <div class="card-container">
        <!-- MOVIE IMAGE -->
        <?php if ($reservation_details): ?>
          <div class="card-img">
            <?php if (!empty($reservation_details['image_url'])): ?>
              <img 
                src="<?php echo htmlspecialchars($reservation_details['image_url'], ENT_QUOTES, 'UTF-8'); ?>" 
                alt="<?php echo htmlspecialchars($reservation_details['title'], ENT_QUOTES, 'UTF-8'); ?>"
                loading="lazy"
                onerror="this.src='img/aa.PNG'"
              />
            <?php else: ?>
              <img src="img/aa.PNG" alt="No image available" />
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- PAYMENT FORM -->
        <div class="card-content">
          <h3>Secure Payment</h3>

          <?php if ($message !== ''): ?>
            <div style="background-color: <?php echo strpos($message, '✅') !== false ? '#28a745' : '#dc3545'; ?>; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
              <strong><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
          <?php endif; ?>

          <?php if (!$reservation_details): ?>
            <div style="background-color: #ffc107; color: #000; padding: 20px; border-radius: 8px; text-align: center;">
              <p>No active reservation found. Please <a href="reservation.php" style="color: #e50914; font-weight: bold;">create a reservation</a> first.</p>
            </div>
          <?php else: ?>
            <!-- RESERVATION SUMMARY -->
            <div class="reservation-summary">
              <h4><?php echo htmlspecialchars($reservation_details['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
              <p><strong>Date:</strong> <?php echo htmlspecialchars(date('M j, Y', strtotime($reservation_details['date'])), ENT_QUOTES, 'UTF-8'); ?></p>
              <p><strong>Time:</strong> <?php echo htmlspecialchars(date('H:i', strtotime($reservation_details['time'])), ENT_QUOTES, 'UTF-8'); ?></p>
              <p><strong>Seats:</strong> <?php echo (int)$reservation_details['seats_reserved']; ?> seat(s)</p>
              <p class="total-amount">Total: $<?php echo number_format($total_amount, 2); ?></p>
            </div>

            <form method="POST" action="paiement.php">
              <input type="hidden" name="reservation_id" value="<?php echo (int)$reservation_id; ?>" />
              <input type="hidden" name="amount" value="<?php echo number_format($total_amount, 2); ?>" />

              <div class="form-row">
                <div class="form-group">
                  <i class="bx bx-user"></i>
                  <input type="text" name="fullname" placeholder="Full Name" value="<?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?>" required />
                </div>

                <div class="form-group">
                  <i class="bx bx-envelope"></i>
                  <input type="email" name="email" placeholder="Email Address" required />
                </div>
              </div>

              <div class="form-group">
                <i class="bx bx-credit-card"></i>
                <select name="payment_method" required>
                  <option value="">-- Select Payment Method --</option>
                  <option value="Credit Card">💳 Credit Card</option>
                  <option value="Debit Card">💳 Debit Card</option>
                  <option value="PayPal">🅿️ PayPal</option>
                  <option value="Apple Pay">🍎 Apple Pay</option>
                  <option value="Google Pay">🔵 Google Pay</option>
                  <option value="Cash">💵 Cash at Counter</option>
                </select>
              </div>

              <input type="submit" value="Confirm Payment" class="btn" />
            </form>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- Scripts -->
    <script src="js/swiper-bundle.min.js"></script>
    <script src="js/main.js"></script>
    <script>
      const dropdownMenu = document.querySelector(".dropdown-menu");

      if (dropdownMenu) {
        dropdownMenu.innerHTML = `
          <li><a href="profile.php" class="login-btn">Profile</a></li>
          <li><a href="logout.php" class="signup-btn">Logout</a></li>
        `;
      }

      document.addEventListener("click", function (event) {
        const userMenu = document.querySelector(".user-menu");
        const userToggle = document.querySelector("#user-toggle");

        if (userMenu && !userMenu.contains(event.target)) {
          if (userToggle) userToggle.checked = false;
        }
      });
    </script>
  </body>
</html>