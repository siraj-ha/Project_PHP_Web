<?php
session_start();
require_once __DIR__ . '/backend/db.php';

if (!isset($_SESSION['user_id'])) {
		header('Location: login.php');
		exit;
}

if (!isset($_SESSION['is_admin']) || (int) $_SESSION['is_admin'] !== 1) {
		header('Location: main.php');
		exit;
}

if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$currentAdminId = (int) $_SESSION['user_id'];
$flashMessage = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

function clean_text(string $value): string
{
		return trim($value);
}

function normalize_status(string $status): string
{
		return in_array($status, ['available', 'coming_soon'], true) ? $status : 'coming_soon';
}

function to_rating(string $value): float
{
		$rating = (float) $value;
		if ($rating < 0) {
				return 0.0;
		}

		if ($rating > 5.0) {
				return 5.0;
		}

		return round($rating, 1);
}

function upload_movie_image(?array $file, string &$errorMessage)
{
		if (!$file || !isset($file['error'])) {
				return null;
		}

		if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
				return null;
		}

		if ((int) $file['error'] !== UPLOAD_ERR_OK) {
				$errorMessage = 'Image upload failed. Please try again.';
				return false;
		}

		$maxSize = 5 * 1024 * 1024;
		if (!isset($file['size']) || (int) $file['size'] <= 0 || (int) $file['size'] > $maxSize) {
				$errorMessage = 'Image must be between 1 byte and 5 MB.';
				return false;
		}

		$tmpName = $file['tmp_name'] ?? '';
		if ($tmpName === '' || !is_uploaded_file($tmpName)) {
				$errorMessage = 'Invalid image upload request.';
				return false;
		}

		$imageInfo = @getimagesize($tmpName);
		if ($imageInfo === false) {
				$errorMessage = 'Only image files are allowed.';
				return false;
		}

		$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
		$extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
		if (!in_array($extension, $allowedExtensions, true)) {
				$errorMessage = 'Allowed image types: jpg, jpeg, png, gif, webp.';
				return false;
		}

		$uploadDir = __DIR__ . '/img/uploads';
		if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
				$errorMessage = 'Could not create upload directory.';
				return false;
		}

		$filename = 'movie_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
		$targetPath = $uploadDir . '/' . $filename;

		if (!move_uploaded_file($tmpName, $targetPath)) {
				$errorMessage = 'Failed to save uploaded image.';
				return false;
		}

		return 'img/uploads/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$postedToken = $_POST['csrf_token'] ?? '';

		if (!hash_equals($_SESSION['csrf_token'], $postedToken)) {
				$flashMessage = 'Invalid request token. Please refresh and try again.';
				$flashType = 'error';
		} else {
				$action = $_POST['action'] ?? '';

				if ($action === 'create_movie') {
						$title = clean_text($_POST['title'] ?? '');
						$description = clean_text($_POST['description'] ?? '');
						$status = normalize_status($_POST['status'] ?? 'coming_soon');
						$trailerUrl = clean_text($_POST['trailer_url'] ?? '');
						$genre = clean_text($_POST['genre'] ?? '');
						$rating = to_rating($_POST['rating'] ?? '0');
						$uploadError = '';
						$uploadedImage = upload_movie_image($_FILES['image_file'] ?? null, $uploadError);

						if ($uploadedImage === false) {
								$flashMessage = $uploadError;
								$flashType = 'error';
						}

						$imageUrl = is_string($uploadedImage) ? $uploadedImage : '';

						if ($flashType !== 'error' && $title === '') {
								$flashMessage = 'Movie title is required.';
								$flashType = 'error';
						} elseif ($flashType !== 'error' && $imageUrl === '') {
								$flashMessage = 'Movie image file is required.';
								$flashType = 'error';
						} elseif ($flashType !== 'error') {
								$stmt = $conn->prepare('INSERT INTO movies (title, description, image_url, status, trailer_url, genre, rating) VALUES (?, ?, ?, ?, ?, ?, ?)');

								if ($stmt) {
										$stmt->bind_param('ssssssd', $title, $description, $imageUrl, $status, $trailerUrl, $genre, $rating);

										if ($stmt->execute()) {
												$flashMessage = 'Movie added successfully.';
												$flashType = 'success';
										} else {
												$flashMessage = 'Could not add movie. Please try again.';
												$flashType = 'error';
										}

										$stmt->close();
								} else {
										$flashMessage = 'Could not prepare create movie request.';
										$flashType = 'error';
								}
						}
				}

				if ($action === 'update_movie') {
						$movieId = isset($_POST['movie_id']) ? (int) $_POST['movie_id'] : 0;
						$title = clean_text($_POST['title'] ?? '');
						$description = clean_text($_POST['description'] ?? '');
						$currentImageUrl = clean_text($_POST['current_image_url'] ?? '');
						$status = normalize_status($_POST['status'] ?? 'coming_soon');
						$trailerUrl = clean_text($_POST['trailer_url'] ?? '');
						$genre = clean_text($_POST['genre'] ?? '');
						$rating = to_rating($_POST['rating'] ?? '0');
						$uploadError = '';
						$uploadedImage = upload_movie_image($_FILES['image_file'] ?? null, $uploadError);

						if ($uploadedImage === false) {
								$flashMessage = $uploadError;
								$flashType = 'error';
						}

						$imageUrl = is_string($uploadedImage) ? $uploadedImage : $currentImageUrl;

						if ($flashType !== 'error' && ($movieId <= 0 || $title === '')) {
								$flashMessage = 'Movie ID and title are required for update.';
								$flashType = 'error';
						} elseif ($flashType !== 'error') {
								$stmt = $conn->prepare('UPDATE movies SET title = ?, description = ?, image_url = ?, status = ?, trailer_url = ?, genre = ?, rating = ? WHERE id = ?');

								if ($stmt) {
										$stmt->bind_param('ssssssdi', $title, $description, $imageUrl, $status, $trailerUrl, $genre, $rating, $movieId);

										if ($stmt->execute()) {
												$flashMessage = 'Movie updated successfully.';
												$flashType = 'success';
										} else {
												$flashMessage = 'Could not update movie. Please try again.';
												$flashType = 'error';
										}

										$stmt->close();
								} else {
										$flashMessage = 'Could not prepare update movie request.';
										$flashType = 'error';
								}
						}
				}

				if ($action === 'delete_movie') {
						$movieId = isset($_POST['movie_id']) ? (int) $_POST['movie_id'] : 0;

						if ($movieId <= 0) {
								$flashMessage = 'Invalid movie selected for deletion.';
								$flashType = 'error';
						} else {
								$stmt = $conn->prepare('DELETE FROM movies WHERE id = ?');

								if ($stmt) {
										$stmt->bind_param('i', $movieId);

										if ($stmt->execute()) {
												$flashMessage = 'Movie deleted successfully.';
												$flashType = 'success';
										} else {
												$flashMessage = 'Could not delete movie. Please try again.';
												$flashType = 'error';
										}

										$stmt->close();
								} else {
										$flashMessage = 'Could not prepare delete movie request.';
										$flashType = 'error';
								}
						}
				}

				if ($action === 'toggle_user_admin') {
						$userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
						$makeAdmin = isset($_POST['make_admin']) ? (int) $_POST['make_admin'] : 0;

						if ($userId <= 0) {
								$flashMessage = 'Invalid user selected.';
								$flashType = 'error';
						} elseif ($userId === $currentAdminId && $makeAdmin === 0) {
								$flashMessage = 'You cannot remove admin role from your own account.';
								$flashType = 'error';
						} else {
								$stmt = $conn->prepare('UPDATE users SET is_admin = ? WHERE id = ?');

								if ($stmt) {
										$stmt->bind_param('ii', $makeAdmin, $userId);

										if ($stmt->execute()) {
												$flashMessage = 'User role updated successfully.';
												$flashType = 'success';
										} else {
												$flashMessage = 'Could not update user role.';
												$flashType = 'error';
										}

										$stmt->close();
								} else {
										$flashMessage = 'Could not prepare user role update request.';
										$flashType = 'error';
								}
						}
				}

				if ($action === 'delete_user') {
						$userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;

						if ($userId <= 0) {
								$flashMessage = 'Invalid user selected for deletion.';
								$flashType = 'error';
						} elseif ($userId === $currentAdminId) {
								$flashMessage = 'You cannot delete your own account.';
								$flashType = 'error';
						} else {
								$stmt = $conn->prepare('DELETE FROM users WHERE id = ?');

								if ($stmt) {
										$stmt->bind_param('i', $userId);

										if ($stmt->execute()) {
												$flashMessage = 'User deleted successfully.';
												$flashType = 'success';
										} else {
												$flashMessage = 'Could not delete user.';
												$flashType = 'error';
										}

										$stmt->close();
								} else {
										$flashMessage = 'Could not prepare delete user request.';
										$flashType = 'error';
								}
						}
				}
		}

		if ($flashMessage !== '') {
				$_SESSION['flash_message'] = $flashMessage;
				$_SESSION['flash_type'] = $flashType;
		}

		$redirectTo = $_SERVER['REQUEST_URI'] ?? 'panel.php';
		$redirectTo = strtok($redirectTo, '#');
		if ($redirectTo === false || $redirectTo === '') {
				$redirectTo = 'panel.php';
		}

		header('Location: ' . $redirectTo);
		exit;
}

$editMovieId = isset($_GET['edit_movie']) ? (int) $_GET['edit_movie'] : 0;
$editMovie = null;

if ($editMovieId > 0) {
		$stmt = $conn->prepare('SELECT id, title, description, image_url, status, trailer_url, genre, rating FROM movies WHERE id = ? LIMIT 1');
		if ($stmt) {
				$stmt->bind_param('i', $editMovieId);
				$stmt->execute();
				$result = $stmt->get_result();
				if ($result && $result->num_rows === 1) {
						$editMovie = $result->fetch_assoc();
				}
				$stmt->close();
		}
}

$moviesPerPage = 10;
$moviePage = isset($_GET['movie_page']) ? (int) $_GET['movie_page'] : 1;
if ($moviePage < 1) {
		$moviePage = 1;
}

$movieTotal = 0;
$movieCountResult = $conn->query('SELECT COUNT(*) AS total FROM movies');
if ($movieCountResult) {
		$movieTotalRow = $movieCountResult->fetch_assoc();
		$movieTotal = (int) ($movieTotalRow['total'] ?? 0);
}

$movieTotalPages = max(1, (int) ceil($movieTotal / $moviesPerPage));
if ($moviePage > $movieTotalPages) {
		$moviePage = $movieTotalPages;
}

$movieOffset = ($moviePage - 1) * $moviesPerPage;
$movies = [];

$moviesStmt = $conn->prepare('SELECT id, title, status FROM movies ORDER BY id DESC LIMIT ? OFFSET ?');
if ($moviesStmt) {
		$moviesStmt->bind_param('ii', $moviesPerPage, $movieOffset);
		$moviesStmt->execute();
		$moviesResult = $moviesStmt->get_result();
		if ($moviesResult) {
				$movies = $moviesResult->fetch_all(MYSQLI_ASSOC);
		}
		$moviesStmt->close();
}

$availableMovies = 0;
$comingSoonMovies = 0;
$availableResult = $conn->query("SELECT COUNT(*) AS total FROM movies WHERE status = 'available'");
if ($availableResult) {
		$availableRow = $availableResult->fetch_assoc();
		$availableMovies = (int) ($availableRow['total'] ?? 0);
}
$comingSoonResult = $conn->query("SELECT COUNT(*) AS total FROM movies WHERE status = 'coming_soon'");
if ($comingSoonResult) {
		$comingSoonRow = $comingSoonResult->fetch_assoc();
		$comingSoonMovies = (int) ($comingSoonRow['total'] ?? 0);
}

$users = [];
$usersResult = $conn->query('SELECT id, full_name, email, is_admin, created_at FROM users ORDER BY id DESC');
if ($usersResult) {
		$users = $usersResult->fetch_all(MYSQLI_ASSOC);
}

function h(string $value): string
{
		return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
	<head>
		<meta charset="UTF-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<title>Rengoku.tv - Control Panel</title>
		<link rel="stylesheet" href="css/panel.css" />
	</head>
	<body class="panel-page">
		<div class="panel-layout">
			<aside class="panel-sidebar">
				<a href="main.php">
					<h2 class="logo">Rengoku<span>.tv</span></h2>
				</a>
				<nav>
					<a href="#dashboardSection" class="active">Dashboard</a>
        			<a href="dashboard.php">Stats</a>
					<a href="#moviesSection">Movies</a>
					<a href="#usersSection">Users</a>
				</nav>
			</aside>

			<main class="panel-main admin-wrapper">
			<header class="topbar" id="dashboardSection">
				<h1>Control Panel</h1>
				<div class="admin"><?php echo h($_SESSION['user_name'] ?? 'Admin'); ?></div>
			</header>

			<?php if ($flashMessage !== ''): ?>
			<div class="flash <?php echo $flashType === 'error' ? 'error' : 'success'; ?>">
				<?php echo h($flashMessage); ?>
			</div>
			<?php endif; ?>

			<section class="section-card">
				<div class="section-header">
					<h2>Overview</h2>
					
				</div>
				<div class="stats-grid">
					<div class="stat-item">
						<h3>Total Movies</h3>
						<p><?php echo $movieTotal > 0 ? (string) $movieTotal : 'Coming Soon'; ?></p>
					</div>
					<div class="stat-item">
						<h3>Available</h3>
						<p><?php echo $availableMovies > 0 ? (string) $availableMovies : 'Coming Soon'; ?></p>
					</div>
					<div class="stat-item">
						<h3>Coming Soon</h3>
						<p><?php echo $comingSoonMovies > 0 ? (string) $comingSoonMovies : 'Coming Soon'; ?></p>
					</div>
					<div class="stat-item">
						<h3>Users</h3>
						<p><?php echo count($users) > 0 ? (string) count($users) : 'Coming Soon'; ?></p>
					</div>
				</div>
			</section>

			<section class="section-card" id="moviesSection">
				<div class="section-header">
					<h2><?php echo $editMovie ? 'Update Movie' : 'Add Movie'; ?></h2>
					
				</div>

				<form method="POST" enctype="multipart/form-data" action="panel.php<?php echo $moviePage > 1 ? '?movie_page=' . $moviePage : ''; ?>">
					<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>" />
					<input type="hidden" name="action" value="<?php echo $editMovie ? 'update_movie' : 'create_movie'; ?>" />
					<?php if ($editMovie): ?>
					<input type="hidden" name="movie_id" value="<?php echo (int) $editMovie['id']; ?>" />
					<input type="hidden" name="current_image_url" value="<?php echo h($editMovie['image_url'] ?? ''); ?>" />
					<?php endif; ?>

					<div class="movie-form-grid">
						<div class="field">
							<label for="title">Title</label>
							<input type="text" id="title" name="title" required value="<?php echo h($editMovie['title'] ?? ''); ?>" />
						</div>

						<div class="field">
							<label for="genre">Genre</label>
							<?php
							$genreOptions = ['Action', 'Adventure', 'Comedy', 'Drama', 'Horror', 'Sci-Fi', 'Fantasy', 'Thriller', 'Crime', 'Documentary', 'Animation'];
							$currentGenre = trim((string) ($editMovie['genre'] ?? ''));
							?>
							<select id="genre" name="genre">
								<option value="">Select genre</option>
								<?php foreach ($genreOptions as $option): ?>
								<option value="<?php echo h($option); ?>" <?php echo $currentGenre === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option>
								<?php endforeach; ?>
								<?php if ($currentGenre !== '' && !in_array($currentGenre, $genreOptions, true)): ?>
								<option value="<?php echo h($currentGenre); ?>" selected><?php echo h($currentGenre); ?></option>
								<?php endif; ?>
							</select>
						</div>

						<div class="field">
							<label for="status">Status</label>
							<select id="status" name="status">
								<option value="available" <?php echo (($editMovie['status'] ?? '') === 'available') ? 'selected' : ''; ?>>Available</option>
								<option value="coming_soon" <?php echo (($editMovie['status'] ?? '') !== 'available') ? 'selected' : ''; ?>>Coming Soon</option>
							</select>
						</div>

						<div class="field">
							<label for="rating">Rating (0 - 5.0)</label>
							<input type="number" id="rating" name="rating" min="0" max="5.0" step="0.1" value="<?php echo h((string) ($editMovie['rating'] ?? '0')); ?>" />
						</div>

						<div class="field">
							<label for="image_file">Image</label>
							<input type="file" id="image_file" name="image_file" accept="image/*" <?php echo $editMovie ? '' : 'required'; ?> />
							<?php if ($editMovie && ($editMovie['image_url'] ?? '') !== ''): ?>
							<small class="muted">Current: <?php echo h($editMovie['image_url']); ?></small>
							<?php endif; ?>
						</div>

						<div class="field">
							<label for="trailer_url">Trailer URL</label>
							<input type="text" id="trailer_url" name="trailer_url" value="<?php echo h($editMovie['trailer_url'] ?? ''); ?>" />
						</div>

						<div class="field" style="grid-column: 1 / -1;">
							<label for="description">Description</label>
							<textarea id="description" name="description"><?php echo h($editMovie['description'] ?? ''); ?></textarea>
						</div>
					</div>

					<div class="btn-row" style="margin-top: 12px;">
						<button type="submit" class="btn btn-primary"><?php echo $editMovie ? 'Update Movie' : 'Add Movie'; ?></button>
						<?php if ($editMovie): ?>
						<a class="btn btn-secondary" href="panel.php#moviesSection">Cancel Edit</a>
						<?php endif; ?>
					</div>
				</form>
			</section>

			<section class="section-card">
				<div class="section-header">
					<h2>Movies List</h2>
					
				</div>

				<?php if (count($movies) === 0): ?>
				<p class="muted">Coming Soon</p>
				<?php else: ?>
				<div class="table-wrap">
					<table>
						<thead>
							<tr>
								<th>ID</th>
								<th>Name</th>
								<th>Status</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($movies as $movie): ?>
							<tr>
								<td><?php echo (int) $movie['id']; ?></td>
								<td><?php echo h($movie['title']); ?></td>
								<td>
									<span class="badge <?php echo $movie['status'] === 'available' ? 'available' : 'coming-soon'; ?>">
										<?php echo $movie['status'] === 'available' ? 'Available' : 'Coming Soon'; ?>
									</span>
								</td>
								<td>
									<div class="btn-row">
										<a class="btn btn-secondary" href="panel.php?movie_page=<?php echo $moviePage; ?>&edit_movie=<?php echo (int) $movie['id']; ?>#moviesSection">Update</a>
										<form method="POST" action="panel.php?movie_page=<?php echo $moviePage; ?>#moviesSection" onsubmit="return confirm('Delete this movie?');">
											<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>" />
											<input type="hidden" name="action" value="delete_movie" />
											<input type="hidden" name="movie_id" value="<?php echo (int) $movie['id']; ?>" />
											<button type="submit" class="btn btn-danger">Delete</button>
										</form>
									</div>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div class="pagination">
					<?php if ($moviePage > 1): ?>
					<a href="panel.php?movie_page=<?php echo $moviePage - 1; ?>#moviesSection">Previous</a>
					<?php endif; ?>

					<?php for ($p = 1; $p <= $movieTotalPages; $p++): ?>
					<span class="<?php echo $p === $moviePage ? 'current' : ''; ?>">
						<?php if ($p === $moviePage): ?>
							<?php echo $p; ?>
						<?php else: ?>
							<a href="panel.php?movie_page=<?php echo $p; ?>#moviesSection"><?php echo $p; ?></a>
						<?php endif; ?>
					</span>
					<?php endfor; ?>

					<?php if ($moviePage < $movieTotalPages): ?>
					<a href="panel.php?movie_page=<?php echo $moviePage + 1; ?>#moviesSection">Next</a>
					<?php endif; ?>
				</div>
				<?php endif; ?>
			</section>

			<section class="section-card" id="usersSection">
				<div class="section-header">
					<h2>Users Control</h2>
					
				</div>

				<?php if (count($users) === 0): ?>
				<p class="muted">Coming Soon</p>
				<?php else: ?>
				<div class="table-wrap">
					<table>
						<thead>
							<tr>
								<th>ID</th>
								<th>Name</th>
								<th>Email</th>
								<th>Role</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($users as $user): ?>
							<tr>
								<td><?php echo (int) $user['id']; ?></td>
								<td><?php echo h($user['full_name']); ?></td>
								<td><?php echo h($user['email']); ?></td>
								<td><?php echo ((int) $user['is_admin'] === 1) ? 'Admin' : 'User'; ?></td>
								<td>
									<div class="btn-row">
										<form method="POST" action="panel.php#usersSection">
											<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>" />
											<input type="hidden" name="action" value="toggle_user_admin" />
											<input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>" />
											<input type="hidden" name="make_admin" value="<?php echo ((int) $user['is_admin'] === 1) ? 0 : 1; ?>" />
											<button type="submit" class="btn btn-secondary">
												<?php echo ((int) $user['is_admin'] === 1) ? 'Make User' : 'Make Admin'; ?>
											</button>
										</form>

										<?php if ((int) $user['id'] !== $currentAdminId): ?>
										<form method="POST" action="panel.php#usersSection" onsubmit="return confirm('Delete this user?');">
											<input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>" />
											<input type="hidden" name="action" value="delete_user" />
											<input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>" />
											<button type="submit" class="btn btn-danger">Delete</button>
										</form>
										<?php endif; ?>
									</div>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>
			</section>
			</main>
		</div>
		<script>
			(function () {
				const flash = document.querySelector('.flash');
				if (!flash) {
					return;
				}

				setTimeout(function () {
					flash.style.transition = 'opacity 0.35s ease';
					flash.style.opacity = '0';

					setTimeout(function () {
						if (flash.parentNode) {
							flash.parentNode.removeChild(flash);
						}
					}, 400);
				}, 4000);
			})();
		</script>
	</body>
</html>
