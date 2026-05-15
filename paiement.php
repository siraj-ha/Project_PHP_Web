<?php
session_start();
require_once __DIR__ . '/backend/db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $reservation_id = (int) $_POST['reservation_id'];
    $payment_method = trim($_POST['payment_method']);
    $amount = (float) $_POST['amount'];

    if ($reservation_id <= 0 || $amount <= 0) {

        $message = "Invalid payment data.";

    } else {

        $sql = "INSERT INTO payments
                (reservation_id, payment_method, amount, payment_status)
                VALUES (?, ?, ?, 'paid')";

        $stmt = $conn->prepare($sql);

        if ($stmt) {

            $stmt->bind_param(
                "isd",
                $reservation_id,
                $payment_method,
                $amount
            );

            if ($stmt->execute()) {

                $message = "✅ Payment successful.";

            } else {

                $message = "❌ Payment failed.";

            }

            $stmt->close();
        }
    }
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <me ta name="viewport" content="width=device-width, initial-scale=1.0" />
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
            <a href="reservation.html" class="nav-link active">Reservations</a>
          </li>
          <li><a href="contact.html" class="nav-link">Contact</a></li>
          <li class="user-menu">
            <input type="checkbox" id="user-toggle" title="User menu toggle" />
            <label for="user-toggle" class="user-icon">
              <i class="bx bx-us er-circle"></i>
              <i class="bx bxs-chevron-down"></i>
            </label>

            <ul class="dropdown-menu">
              <li><a href="login.php" class="login-btn">Login</a></li>
              <li><a href="signup.php" class="signup-btn">Sign Up</a></li>
            </ul>
          </li>
        </ul>
      </div>
    </header>


    <!-- PAYMENT SECTION -->
    <section class="banner">



        <div class="card-container">

            <!-- IMAGE -->
            <div class="card-img"></div>

            <!-- FORM -->
            <div class="card-content">

                <h3>Pay now !</h3>

                <?php
                if($message != ""){
                    echo "<p style='color:#fa5353; margin-bottom:20px;'>$message</p>";
                }
                ?>

                <form method="POST" action="">

                    <div class="form-row">

                        <div class="form-group">
                            <i class='bx bx-user'></i>
                            <input type="text" name="fullname" placeholder="Full Name" required>
                        </div>

                        <div class="form-group">
                            <i class='bx bx-envelope'></i>
                            <input type="text" name="email" placeholder="Email Address" required>
                        </div>

                    </div>

                    <div class="form-row">

                    
                        

                    </div>

                    <div class="form-group">
                        <i class='bx bx-credit-card'></i>

                        <select name="payment_method" required>
                            <option value="">Payment Method</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="PayPal">PayPal</option>
                            <option value="Cash">Cash</option>
                        </select>
                    </div>

                    <input type="submit" value="Confirm Payment">

                </form>

            </div>

        </div>

    </section>

</body>
</html>