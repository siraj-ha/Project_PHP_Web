<?php
require_once __DIR__ . '/backend/db.php';

function h(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function extract_youtube_video_id(string $url): string
{
	$url = trim($url);
	if ($url === '') {
		return '';
	}

	$patterns = [
		'/youtu\.be\/([A-Za-z0-9_-]{11})/i',
		'/youtube\.com\/watch\?v=([A-Za-z0-9_-]{11})/i',
		'/youtube\.com\/embed\/([A-Za-z0-9_-]{11})/i',
		'/youtube\.com\/shorts\/([A-Za-z0-9_-]{11})/i',
	];

	foreach ($patterns as $pattern) {
		if (preg_match($pattern, $url, $matches)) {
			return $matches[1];
		}
	}

	if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url)) {
		return $url;
	}

	return '';
}

$movieId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$movie = null;

if ($movieId > 0) {
	$stmt = $conn->prepare('SELECT id, title, description, image_url, status, trailer_url, genre, rating FROM movies WHERE id = ? LIMIT 1');
	if ($stmt) {
		$stmt->bind_param('i', $movieId);
		$stmt->execute();
		$result = $stmt->get_result();
		if ($result && $result->num_rows === 1) {
			$movie = $result->fetch_assoc();
		}
		$stmt->close();
	}
}

$trailerVideoId = $movie ? extract_youtube_video_id((string) ($movie['trailer_url'] ?? '')) : '';
$embedUrl = $trailerVideoId !== '' ? 'https://www.youtube-nocookie.com/embed/' . $trailerVideoId . '?rel=0&modestbranding=1' : '';
?>
<!doctype html>
<html lang="en">
	<head>
		<meta charset="UTF-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<title><?php echo $movie ? h($movie['title']) . ' - Rengoku.tv' : 'Movie Not Found - Rengoku.tv'; ?></title>
		<link rel="shortcut icon" href="icon.ico" type="image/x-icon" />
		<link rel="stylesheet" href="css/style.css" />
		<style>
			body {
				background: radial-gradient(circle at top, #2b2230 0, #1b182b 45%, #101018 100%);
			}

			.details-page {
				padding: 110px 0 60px;
			}

			.details-shell {
				background: rgba(20, 20, 28, 0.9);
				border: 1px solid rgba(255, 255, 255, 0.08);
				border-radius: 24px;
				padding: 24px;
				box-shadow: 0 20px 60px rgba(0, 0, 0, 0.35);
			}

			.details-grid {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 24px;
				align-items: start;
			}

			.poster {
				width: 100%;
				border-radius: 18px;
				overflow: hidden;
				background: #0f0f14;
				aspect-ratio: 3 / 4;
			}

			.poster img {
				width: 100%;
				height: 100%;
				object-fit: cover;
				display: block;
			}

			.movie-meta h1 {
				font-size: clamp(2rem, 4vw, 3.4rem);
				margin-bottom: 10px;
				line-height: 1.05;
			}

			.meta-row {
				display: flex;
				gap: 10px;
				flex-wrap: wrap;
				margin: 14px 0 18px;
			}

			.pill {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				padding: 8px 12px;
				border-radius: 999px;
				background: rgba(255, 255, 255, 0.08);
				color: #f5f5f5;
				font-size: 0.92rem;
			}

			.description {
				color: #d6d6d6;
				line-height: 1.75;
				font-size: 1rem;
			}

			.trailer-section {
				margin-top: 28px;
			}

			.trailer-section h2 {
				font-size: 1.4rem;
				margin-bottom: 14px;
			}

			.video-frame {
				position: relative;
				width: 100%;
				padding-top: 56.25%;
				border-radius: 18px;
				overflow: hidden;
				background: #000;
			}

			.video-frame iframe {
				position: absolute;
				inset: 0;
				width: 100%;
				height: 100%;
				border: 0;
			}

			.back-link {
				display: inline-block;
				margin-bottom: 18px;
				color: #fff;
				text-decoration: none;
				background: rgba(255, 255, 255, 0.08);
				padding: 10px 14px;
				border-radius: 999px;
			}

			.error-box {
				padding: 30px;
				border-radius: 18px;
				background: rgba(20, 20, 28, 0.9);
				border: 1px solid rgba(255, 255, 255, 0.08);
			}

			@media (max-width: 900px) {
				.details-grid {
					grid-template-columns: 1fr;
				}
			}
		</style>
	</head>
	<body>
		<header>
			<div class="nav container">
				<a href="Giyu.php" class="logo">Rengoku<span>.tv</span></a>
			</div>
		</header>

		<main class="details-page container">
			<a class="back-link" href="Giyu.php">Back to home</a>

			<?php if (!$movie): ?>
			<div class="error-box">
				<h1>Movie not found</h1>
				<p style="margin-top: 10px; color: #d6d6d6;">The movie you selected does not exist or was removed.</p>
			</div>
			<?php else: ?>
			<section class="details-shell">
				<div class="details-grid">
					<div class="poster">
						<img src="<?php echo h($movie['image_url'] ?: 'img/aa.PNG'); ?>" alt="<?php echo h($movie['title']); ?>" />
					</div>

					<div class="movie-meta">
						<h1><?php echo h($movie['title']); ?></h1>
						<div class="meta-row">
							<span class="pill">Genre: <?php echo h($movie['genre'] ?: 'Unknown'); ?></span>
							<span class="pill">Rating: <?php echo h((string) ($movie['rating'] ?? '0')); ?>/5</span>
							<span class="pill"><?php echo (($movie['status'] ?? '') === 'available') ? 'Available' : 'Coming Soon'; ?></span>
						</div>
						<p class="description"><?php echo nl2br(h($movie['description'] ?: 'No description available.')); ?></p>
					</div>
				</div>

				<div class="trailer-section">
					<h2>Trailer</h2>
					<?php if ($embedUrl !== ''): ?>
					<div class="video-frame">
						<iframe
							src="<?php echo h($embedUrl); ?>"
							title="<?php echo h($movie['title']); ?> trailer"
							allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
							allowfullscreen
						></iframe>
					</div>
					<?php else: ?>
					<div class="error-box">
						<p style="margin: 0; color: #d6d6d6;">Trailer not available yet.</p>
					</div>
					<?php endif; ?>
				</div>
			</section>
			<?php endif; ?>
		</main>
	</body>
</html>
