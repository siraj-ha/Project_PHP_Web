<?php
session_start();
require_once __DIR__ . '/backend/db.php';

if (!isset($_SESSION['user_id'])) {
	header('Location: login.php');
	exit;
}

if (!isset($_SESSION['is_admin']) || (int) $_SESSION['is_admin'] !== 1) {
	header('Location: Giyu.php');
	exit;
}

function h(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$adminName = (string) ($_SESSION['user_name'] ?? 'Admin');

$stats = [
	'movies' => 0,
	'users' => 0,
	'available' => 0,
	'comingSoon' => 0,
	'avgRating' => 0.0,
];

$result = $conn->query('SELECT COUNT(*) AS total FROM movies');
if ($result) {
	$stats['movies'] = (int) (($result->fetch_assoc()['total'] ?? 0));
}

$result = $conn->query('SELECT COUNT(*) AS total FROM users');
if ($result) {
	$stats['users'] = (int) (($result->fetch_assoc()['total'] ?? 0));
}

$result = $conn->query("SELECT COUNT(*) AS total FROM movies WHERE status = 'available'");
if ($result) {
	$stats['available'] = (int) (($result->fetch_assoc()['total'] ?? 0));
}

$result = $conn->query("SELECT COUNT(*) AS total FROM movies WHERE status = 'coming_soon'");
if ($result) {
	$stats['comingSoon'] = (int) (($result->fetch_assoc()['total'] ?? 0));
}

$result = $conn->query('SELECT AVG(rating) AS avg_rating FROM movies WHERE rating > 0');
if ($result) {
	$stats['avgRating'] = (float) (($result->fetch_assoc()['avg_rating'] ?? 0));
}

$genreLabels = [];
$genreValues = [];
$result = $conn->query("SELECT COALESCE(NULLIF(TRIM(genre), ''), 'Unknown') AS label, COUNT(*) AS total FROM movies GROUP BY label ORDER BY total DESC, label ASC");
if ($result) {
	while ($row = $result->fetch_assoc()) {
		$genreLabels[] = $row['label'];
		$genreValues[] = (int) $row['total'];
	}
}

$statusLabels = [];
$statusValues = [];
$result = $conn->query("SELECT CASE WHEN status = 'available' THEN 'Available' WHEN status = 'coming_soon' THEN 'Coming Soon' ELSE 'Other' END AS label, COUNT(*) AS total FROM movies GROUP BY label ORDER BY total DESC, label ASC");
if ($result) {
	while ($row = $result->fetch_assoc()) {
		$statusLabels[] = $row['label'];
		$statusValues[] = (int) $row['total'];
	}
}

$topMovies = [];
$result = $conn->query('SELECT id, title, genre, status, rating, image_url FROM movies ORDER BY rating DESC, created_at DESC LIMIT 6');
if ($result) {
	while ($row = $result->fetch_assoc()) {
		$topMovies[] = $row;
	}
}

$recentMovies = [];
$result = $conn->query('SELECT id, title, genre, status, rating, created_at FROM movies ORDER BY created_at DESC LIMIT 6');
if ($result) {
	while ($row = $result->fetch_assoc()) {
		$recentMovies[] = $row;
	}
}

$recentUsers = [];
$result = $conn->query('SELECT id, full_name, email, is_admin, created_at FROM users ORDER BY created_at DESC LIMIT 6');
if ($result) {
	while ($row = $result->fetch_assoc()) {
		$recentUsers[] = $row;
	}
}

$dashboardPayload = [
	'genre' => [
		'labels' => $genreLabels,
		'values' => $genreValues,
	],
	'status' => [
		'labels' => $statusLabels,
		'values' => $statusValues,
	],
];
?>
<!doctype html>
<html lang="en">
	<head>
		<meta charset="UTF-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<title>Rengoku.tv - Admin Dashboard</title>
		<link rel="shortcut icon" href="icon.ico" type="image/x-icon" />
		<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
		<link rel="stylesheet" href="css/dashboard.css?v=1" />
	</head>
	<body>
		<div class="layout">
			<aside class="sidebar">
				<a class="brand" href="Giyu.php">
					<h1>Rengoku.tv</h1>
					
				</a>

				<nav class="nav">
					<a href="#overview">Overview</a>
					<a href="#charts">Charts</a>
					<a href="#movies">Movies</a>
					<a href="#users">Users</a>
					<a href="panel.php">Control Panel</a>
					
				</nav>
			</aside>

			<main class="main">
				<section class="hero" id="overview">
					<div>
						<span class="badge warn">Admin dashboard</span>
						<h2>Welcome back, <?php echo h($adminName); ?></h2>
						
					</div>

					<div class="hero-actions">
						<a class="btn primary" href="panel.php">Open Control Panel</a>
						<a class="btn" href="movie-details.php?id=1">Open a movie</a>
					</div>
				</section>

				<section class="metrics">
					<div class="metric">
						<div class="label">Total Movies</div>
						<div class="value"><?php echo number_format($stats['movies']); ?></div>
						
					</div>
					<div class="metric">
						<div class="label">Available</div>
						<div class="value"><?php echo number_format($stats['available']); ?></div>
						
					</div>
					<div class="metric">
						<div class="label">Coming Soon</div>
						<div class="value"><?php echo number_format($stats['comingSoon']); ?></div>
						
					</div>
					<div class="metric">
						<div class="label">Users</div>
						<div class="value"><?php echo number_format($stats['users']); ?></div>
						
					</div>
				</section>

				<section class="grid two" id="charts">
					<div class="panel">
						<div class="panel-header">
							<h3>Movies by Genre</h3>
							
						</div>
						<div class="panel-body chart-wrap">
							<canvas id="genreChart"></canvas>
						</div>
					</div>

					<div class="panel">
						<div class="panel-header">
							<h3>Movie Status</h3>
							
						</div>
						<div class="panel-body chart-wrap">
							<canvas id="statusChart"></canvas>
						</div>
					</div>
				</section>

				<div class="spacer"></div>

				<section class="grid two" id="movies">
					<div class="panel">
						<div class="panel-header">
							<h3>Top Rated Movies</h3>
							
						</div>
						<div class="panel-body">
							<?php if (count($topMovies) > 0): ?>
							<div class="cards">
								<?php foreach ($topMovies as $movie): ?>
								<article class="movie-card">
									<a href="movie-details.php?id=<?php echo (int) $movie['id']; ?>">
										<img src="<?php echo h($movie['image_url'] ?: 'img/aa.PNG'); ?>" alt="<?php echo h($movie['title']); ?>" />
									</a>
									<div class="card-content">
										<div class="title-row">
											<h4><?php echo h($movie['title']); ?></h4>
											<span class="badge <?php echo ($movie['status'] === 'available') ? 'good' : 'warn'; ?>">
												<?php echo ($movie['status'] === 'available') ? 'Available' : 'Coming Soon'; ?>
											</span>
										</div>
										<div class="meta">
											<span>Genre: <?php echo h($movie['genre'] ?: 'Unknown'); ?></span>
											<span>Rating: <?php echo number_format((float) $movie['rating'], 1); ?>/5</span>
										</div>
									</div>
								</article>
								<?php endforeach; ?>
							</div>
							<?php else: ?>
							<p class="empty-state">No movie records yet.</p>
							<?php endif; ?>
						</div>
					</div>

					<div class="panel">
						<div class="panel-header">
							<h3>Recent Movies</h3>
							
						</div>
						<div class="panel-body">
							<?php if (count($recentMovies) > 0): ?>
							<div class="list">
								<?php foreach ($recentMovies as $movie): ?>
								<div class="list-item">
									<div>
										<h4><?php echo h($movie['title']); ?></h4>
										<p><?php echo h($movie['genre'] ?: 'Unknown'); ?> • Rating <?php echo number_format((float) $movie['rating'], 1); ?>/5</p>
									</div>
									<span class="badge <?php echo ($movie['status'] === 'available') ? 'good' : 'warn'; ?>">
										<?php echo ($movie['status'] === 'available') ? 'Available' : 'Coming Soon'; ?>
									</span>
								</div>
								<?php endforeach; ?>
							</div>
							<?php else: ?>
							<p class="empty-state">No recent movie records yet.</p>
							<?php endif; ?>
						</div>
					</div>
				</section>

				<div class="spacer"></div>

				<section class="grid two" id="users">
					<div class="panel">
						<div class="panel-header">
							<h3>Recent Users</h3>
							
						</div>
						<div class="panel-body">
							<?php if (count($recentUsers) > 0): ?>
							<div class="list">
								<?php foreach ($recentUsers as $user): ?>
								<div class="list-item">
									<div>
										<h4><?php echo h($user['full_name']); ?></h4>
										<p><?php echo h($user['email']); ?></p>
									</div>
									<span class="badge <?php echo ((int) $user['is_admin'] === 1) ? 'good' : 'warn'; ?>">
										<?php echo ((int) $user['is_admin'] === 1) ? 'Admin' : 'User'; ?>
									</span>
								</div>
								<?php endforeach; ?>
							</div>
							<?php else: ?>
							<p class="empty-state">No users found.</p>
							<?php endif; ?>
						</div>
					</div>

					<div class="panel">
						<div class="panel-header">
							<h3>Site Summary</h3>
							
						</div>
						<div class="panel-body">
							<div class="stack">
								<div class="list-item" style="padding-top: 0;">
									<div>
										<h4>Average Rating</h4>
										<p>Average rating across all rated movies</p>
									</div>
									<strong><?php echo $stats['avgRating'] > 0 ? number_format($stats['avgRating'], 1) . '/5' : 'Coming Soon'; ?></strong>
								</div>
								<div class="list-item">
									<div>
										<h4>Website Source</h4>
										<p>Data pulled directly from MySQL</p>
									</div>
									<strong>Live</strong>
								</div>
								<div class="list-item">
									<div>
										<h4>Reservations</h4>
										<p>No reservations table exists yet</p>
									</div>
									<strong>Not connected</strong>
								</div>
							</div>
						</div>
					</div>
				</section>

				<script>
					window.dashboardData = <?php echo json_encode($dashboardPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
				</script>
				<script src="js/dahsboard.js"></script>
			</main>
		</div>
	</body>
</html>
