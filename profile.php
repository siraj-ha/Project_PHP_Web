<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/backend/db.php';

$user_id = (int) $_SESSION['user_id'];
$message = '';
$error = '';

$conn->query('ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT NULL');

$query = 'SELECT id, full_name, email, phone, bio, created_at FROM users WHERE id = ? LIMIT 1';
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die('User not found');
}

$member_since = date('Y');
if (!empty($user['created_at'])) {
    $created_timestamp = strtotime($user['created_at']);
    if ($created_timestamp !== false) {
        $member_since = date('Y', $created_timestamp);
    }
}

$full_name = $user['full_name'] ?? 'User';
$first_char = $full_name !== '' ? substr($full_name, 0, 1) : '';
$initial = $first_char !== '' ? strtoupper($first_char) : 'U';

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <title>My Profile | Dashboard</title>
    <link rel="shortcut icon" href="icon.ico" type="image/x-icon" />
    <link rel="stylesheet" href="css/profile.css" />
  </head>
  <body>
    <div class="profile-wrapper">
      <div class="profile-card">
        <div class="profile-header">
          <div class="avatar-large"><?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></div>
          <h2><?php echo htmlspecialchars($user['full_name'] ?? 'User', ENT_QUOTES, 'UTF-8'); ?></h2>
          <div class="profile-badge">Member since <?php echo htmlspecialchars($member_since, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>

        <div class="profile-body">
          <?php if ($message !== ''): ?>
            <div class="alert alert-success">
              <span class="alert-icon">OK</span> <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
          <?php endif; ?>

          <?php if ($error !== ''): ?>
            <div class="alert alert-error">
              <span class="alert-icon">!</span> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
          <?php endif; ?>

          <div class="info-grid">
            <div class="info-row">
              <div class="info-label">FULL NAME</div>
              <div class="info-value"><?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>

            <div class="info-row">
              <div class="info-label">EMAIL ADDRESS</div>
              <div class="info-value">
                <?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>
                <div class="text-muted">Verified account</div>
              </div>
            </div>

            <div class="info-row">
              <div class="info-label">PHONE NUMBER</div>
              <div class="info-value <?php echo empty($user['phone']) ? 'empty-field' : ''; ?>">
                <?php if (!empty($user['phone'])): ?>
                  <?php echo htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8'); ?>
                <?php else: ?>
                  Not provided
                  <div class="text-muted">You can add phone later</div>
                <?php endif; ?>
              </div>
            </div>

            <div class="info-row">
              <div class="info-label">BIOGRAPHY</div>
              <div class="info-value bio-text">
                <?php if (!empty($user['bio'])): ?>
                  <?php echo nl2br(htmlspecialchars($user['bio'], ENT_QUOTES, 'UTF-8')); ?>
                <?php else: ?>
                  <span class="empty-field">No bio added yet. Tell something about yourself.</span>
                <?php endif; ?>
              </div>
            </div>

            <div class="info-row">
              <div class="info-label">PROFILE ID</div>
              <div class="info-value">
                <span class="mono-id">#<?php echo htmlspecialchars((string) $user['id'], ENT_QUOTES, 'UTF-8'); ?></span>
                <div class="text-muted">Unique identifier</div>
              </div>
            </div>
          </div>

          <div class="action-buttons">
            <a href="Giyu.html" class="btn btn-secondary">Back to Home</a>
            <div class="edit-icon">Profile managed by system</div>
          </div>

          <hr />
          <div class="footer-note">Secure profile . data protected</div>
        </div>
      </div>
    </div>
  </body>
</html>
