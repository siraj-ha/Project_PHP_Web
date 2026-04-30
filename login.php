<?php
session_start();
require_once __DIR__ . '/backend/db.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';

  if ($email === '' || $password === '') {
    $error_message = 'Please enter both email and password.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error_message = 'Please enter a valid email address.';
  } else {
    $stmt = $conn->prepare('SELECT id, full_name, email, password, is_admin FROM users WHERE email = ? LIMIT 1');

    if (!$stmt) {
      $error_message = 'Something went wrong. Please try again.';
    } else {
      $stmt->bind_param('s', $email);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
          $_SESSION['user_id'] = (int) $user['id'];
          $_SESSION['user_name'] = $user['full_name'];
          $_SESSION['user_email'] = $user['email'];
          $_SESSION['is_admin'] = (int) $user['is_admin'];

          if ((int) $user['is_admin'] === 1) {
            header('Location: panel.php');
          } else {
            header('Location: main.php');
          }
          exit;
        }
      }

      $error_message = 'Invalid email or password.';
      $stmt->close();
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login - Rengoku.tv</title>
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
      rel="stylesheet"
    />
    <link rel="icon" type="image/png" href="icon.ico" />
    <link rel="stylesheet" href="css/auth.css" />
  </head>
  <header>
    <div class="nav container">
      <a href="main.php" class="logo">Rengoku<span>.tv</span></a>
    </div>
  </header>
  <body>
    <div class="login-container">
      <div class="login-header">
        <h2>🎬 Welcome Back</h2>
        <p>Login to your account</p>
      </div>

      <?php if ($error_message !== ''): ?>
      <div class="alert alert-danger" role="alert">
        <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="login.php">
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
          <label for="password" class="form-label">Password</label>
          <input
            type="password"
            class="form-control"
            id="password"
            name="password"
            placeholder="Enter your password"
            required
          />
        </div>

        <div class="forgot-password">
          <a href="#">Forgot Password?</a>
        </div>

        <button type="submit" class="btn btn-login w-100 btn-lg text-white">
          Login
        </button>
      </form>

      <div class="signup-link">
        <p>Don't have an account? <a href="signup.php">Sign up here</a></p>
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
