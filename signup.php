<?php
require_once __DIR__ . '/backend/db.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $full_name = trim($_POST['full_name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $password = $_POST['password'] ?? '';
  $confirm_password = $_POST['confirm_password'] ?? '';

  if ($full_name === '' || $email === '' || $password === '' || $confirm_password === '') {
    $error_message = 'Please fill in all required fields.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error_message = 'Please enter a valid email address.';
  } elseif ($password !== $confirm_password) {
    $error_message = 'Passwords do not match.';
  } else {
    $check_stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');

    if (!$check_stmt) {
      $error_message = 'Something went wrong. Please try again.';
    } else {
      $check_stmt->bind_param('s', $email);
      $check_stmt->execute();
      $check_result = $check_stmt->get_result();

      if ($check_result->num_rows > 0) {
        $error_message = 'This email is already registered.';
      } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $insert_stmt = $conn->prepare('INSERT INTO users (full_name, email, phone, password) VALUES (?, ?, ?, ?)');

        if (!$insert_stmt) {
          $error_message = 'Could not create account. Please try again.';
        } else {
          $insert_stmt->bind_param('ssss', $full_name, $email, $phone, $hashed_password);

          if ($insert_stmt->execute()) {
            $success_message = 'Account created successfully. You can now log in.';
          } else {
            $error_message = 'Could not create account. Please try again.';
          }

          $insert_stmt->close();
        }
      }

      $check_stmt->close();
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sign Up - Rengoku.tv</title>

    <!-- Bootstrap -->
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
      rel="stylesheet"
    />

    <!-- Box Icons -->
    <link
      href="https://cdn.boxicons.com/3.0.3/fonts/boxicons.min.css"
      rel="stylesheet"
    />

    <link rel="icon" type="image/png" href="icon.ico" />
    <link rel="stylesheet" href="css/auth.css" />
  </head>

  <body>
    <header>
      <div class="nav container">
        <a href="Giyu.php" class="logo">Rengoku<span>.tv</span></a>
        <!-- Navigation Links -->
      </div>
    </header>

    <div class="signup-container">
      <div class="signup-header">
        <h2>🎬 Create Account</h2>
        <p>Join us for the best cinema experience</p>
      </div>

      <?php if ($success_message !== ''): ?>
      <div class="alert alert-success" role="alert">
        <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
      </div>
      <?php endif; ?>

      <?php if ($error_message !== ''): ?>
      <div class="alert alert-danger" role="alert">
        <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="signup.php">
        <div class="mb-3">
          <label for="fullName" class="form-label">Full Name</label>
          <input
            type="text"
            class="form-control"
            id="fullName"
            name="full_name"
            value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name'], ENT_QUOTES, 'UTF-8') : ''; ?>"
            placeholder="Enter your full name"
            required
          />
        </div>

        <div class="mb-3">
          <label for="email" class="form-label">Email Address</label>
          <input
            type="email"
            class="form-control"
            id="email"
            name="email"
            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : ''; ?>"
            placeholder="Enter your email"
            required
          />
        </div>

        <div class="mb-3">
          <label for="phone" class="form-label">Phone Number</label>
          <input
            type="tel"
            class="form-control"
            id="phone"
            name="phone"
            value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone'], ENT_QUOTES, 'UTF-8') : ''; ?>"
            placeholder="Enter your phone number"
          />
        </div>

        <div class="mb-3">
          <label for="password" class="form-label">Password</label>
          <input
            type="password"
            class="form-control"
            id="password"
            name="password"
            placeholder="Create a password"
            required
          />
        </div>

        <div class="mb-3">
          <label for="confirmPassword" class="form-label"
            >Confirm Password</label
          >
          <input
            type="password"
            class="form-control"
            id="confirmPassword"
            name="confirm_password"
            placeholder="Confirm your password"
            required
          />
        </div>

        <div class="mb-3 form-check">
          <input type="checkbox" class="form-check-input" id="terms" required />
          <label class="form-check-label" for="terms">
            I agree to the
            <a href="#" class="text-decoration-none">Terms & Conditions</a>
          </label>
        </div>

        <button type="submit" class="btn btn-signup w-100 btn-lg text-white">
          Sign Up
        </button>
      </form>

      <div class="login-link">
        <p>Already have an account? <a href="login.php">Login here</a></p>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      document.addEventListener("click", function (event) {
        const userMenu = document.querySelector(".user-menu");
        const userToggle = document.querySelector("#user-toggle");

        if (userMenu && !userMenu.contains(event.target)) {
          userToggle.checked = false;
        }
      });
    </script>
  </body>
</html>
