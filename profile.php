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

$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT NULL");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_full_name = trim($_POST['full_name'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $new_phone = trim($_POST['phone'] ?? '');
    
    $new_phone = $new_phone === '' ? null : $new_phone;

    if ($new_full_name === '') {
        $error = 'Full name is required.';
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($new_phone !== null && !preg_match('/^[0-9+\-\s]{7,20}$/', $new_phone)) {
        $error = 'Phone number can only contain digits, spaces, + and -.';
    } else {
        // Check if email already exists for another user
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_stmt->bind_param("si", $new_email, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = 'Email address is already in use by another account.';
        } else {
            $update_query = 'UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ? LIMIT 1';
            $update_stmt = $conn->prepare($update_query);

            if ($update_stmt) {
                $update_stmt->bind_param('sssi', $new_full_name, $new_email, $new_phone, $user_id);

                if ($update_stmt->execute()) {
                    $message = 'Profile updated successfully.';
                } else {
                    $error = 'Could not update profile right now. Please try again.';
                }

                $update_stmt->close();
            } else {
                $error = 'Could not prepare profile update request.';
            }
        }
        $check_stmt->close();
    }
}

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
$first_char = $full_name !== '' ? mb_substr($full_name, 0, 1) : '';
$initial = $first_char !== '' ? mb_strtoupper($first_char) : 'U';

$reservations = [];
$reservations_query = '
    SELECT
        r.id,
        r.total_price,
        r.seats_reserved,
        r.status,
        r.created_at,
        s.date AS screening_date,
        s.time AS screening_time,
        m.id AS movie_id,
        m.title,
        m.genre,
        m.image_url
    FROM reservations r
    JOIN screenings s ON r.screening_id = s.id
    JOIN movies m ON s.movie_id = m.id
    WHERE r.user_id = ?
    ORDER BY r.id DESC
';
$reservations_stmt = $conn->prepare($reservations_query);

if ($reservations_stmt) {
    $reservations_stmt->bind_param('i', $user_id);
    $reservations_stmt->execute();
    $reservations_result = $reservations_stmt->get_result();

    if ($reservations_result) {
        $reservations = $reservations_result->fetch_all(MYSQLI_ASSOC);
    }

    $reservations_stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <title>My Profile</title>
    <link rel="shortcut icon" href="icon.ico" type="image/x-icon" />
    <link rel="stylesheet" href="css/profile.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
</head>
<body>
    <div class="profile-wrapper">
        <div class="profile-card">
            <div class="profile-header">
                <div class="avatar-large"><?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></div>
                <h2><?php echo htmlspecialchars($user['full_name'] ?? 'User', ENT_QUOTES, 'UTF-8'); ?></h2>
                <span class="profile-badge">Member since <?php echo htmlspecialchars($member_since, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>

            <div class="profile-body">
                <?php if ($message !== ''): ?>
                    <div class="alert alert-success">
                        <span class="alert-icon">✓</span> <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-error">
                        <span class="alert-icon">⚠</span> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-label">User ID</div>
                        <div class="info-value mono-id">#<?php echo (int) $user['id']; ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Phone</div>
                        <div class="info-value">
                            <?php if (!empty($user['phone'])): ?>
                                <?php echo htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php else: ?>
                                <span class="empty-field">Not provided</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <details class="edit-profile-panel">
                    <summary>Edit Profile</summary>
                    <form method="POST" action="" class="edit-profile-form">
                        <div class="form-group">
                            <label for="full_name">Full Name <span class="required">*</span></label>
                            <input
                                id="full_name"
                                name="full_name"
                                type="text"
                                required
                                value="<?php echo htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            />
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address <span class="required">*</span></label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                required
                                value="<?php echo htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            />
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input
                                id="phone"
                                name="phone"
                                type="tel"
                                value="<?php echo htmlspecialchars($user['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            />
                            <div class="helper-text">Format: digits, spaces, + and - (7-20 characters)</div>
                        </div>

                        <button type="submit" name="update_profile">Save Changes</button>
                    </form>
                </details>

                <section class="reservation-history">
                    <div class="reservation-history-header">
                        <h3>My Reservations</h3>
                        <span><?php echo count($reservations); ?> booking(s)</span>
                    </div>

                    <?php if (empty($reservations)): ?>
                        <div class="reservation-empty">
                            You have not made any reservations yet.
                        </div>
                    <?php else: ?>
                        <div class="reservation-table-wrap">
                            <table class="reservation-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Movie</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Seats</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reservations as $reservation): ?>
                                        <tr>
                                            <td>#<?php echo (int) $reservation['id']; ?></td>
                                            <td><?php echo htmlspecialchars($reservation['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars(date('M j, Y', strtotime($reservation['screening_date'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($reservation['screening_time'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo (int) ($reservation['seats_reserved'] ?? 0); ?></td>
                                            <td>$<?php echo number_format((float) $reservation['total_price'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
                <hr />

                <div class="action-buttons">
                    <a href="main.php" class="btn btn-secondary"> Home</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>