<?php
require_once 'backend/db.php';


$random_movie = null;
$random_result = $conn->query("SELECT id, title, image_url, genre, rating FROM movies WHERE status = 'available' ORDER BY RAND() LIMIT 1");
if ($random_result && $random_result->num_rows > 0) {
  $random_movie = $random_result->fetch_assoc();
}


$available_movies = [];
$available_result = $conn->query("SELECT id, title, image_url, genre, rating FROM movies WHERE status = 'available' ORDER BY created_at DESC");
if ($available_result) {
  $available_movies = $available_result->fetch_all(MYSQLI_ASSOC);
}


$coming_soon_movies = [];
$coming_soon_result = $conn->query("SELECT id, title, image_url, genre FROM movies WHERE status = 'coming_soon' ORDER BY created_at DESC");
if ($coming_soon_result) {
  $coming_soon_movies = $coming_soon_result->fetch_all(MYSQLI_ASSOC);
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Rengoku.tv</title>


  <link rel="shortcut icon" href="icon.ico" type="image/x-icon" />


  <link rel="stylesheet" href="css/style.v2.css" />
  <link rel="stylesheet" href="css/swiper-bundle.min.css" />


  <link href="https://cdn.boxicons.com/3.0.3/fonts/basic/boxicons.min.css" rel="stylesheet" />
</head>

<body>
  <header>
    <div class="nav container">
      <a href="main.php" class="logo">Rengoku<span>.tv</span></a>

      <ul class="nav-menu">
        <li><a href="#home" class="nav-link active">Home</a></li>
        <li><a href="#movies" class="nav-link">Movies</a></li>
        <li><a href="#trending" class="nav-link">Coming Soon</a></li>
        <li><a href="contact.html" class="nav-link">Contact</a></li>


        <li class="user-menu">

          <input type="checkbox" id="user-toggle" title="User menu toggle" />


          <label for="user-toggle" class="user-icon">
            <i class="bx bx-user-circle"></i>
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

  <section class="home container" id="home">
    <?php if ($random_movie): ?>
      <img src="<?php echo htmlspecialchars($random_movie['image_url']); ?>"
        alt="<?php echo htmlspecialchars($random_movie['title']); ?>" />
      <div class="home-text">
        <h1><?php echo htmlspecialchars($random_movie['title']); ?>
          <br /><?php echo htmlspecialchars($random_movie['genre'] ?? ''); ?></h1>
        <a href="movie-details.php?id=<?php echo $random_movie['id']; ?>" class="btn">View Details</a>
      </div>
    <?php else: ?>
      <img src="img/logo.PNG" />
      <div class="home-text">
        <h1>Rengoku.tv <br />Movies Coming Soon</h1>
        <a href="#movies" class="btn">Browse Movies</a>
      </div>
    <?php endif; ?>
  </section>


  <section class="trending movies container" id="movies">
    <div class="heading">
      <i class="bx bxs-flame"></i>
      <h2>Movies</h2>
    </div>

    <?php if (count($available_movies) > 0): ?>
      <div class="trending-content swiper">
        <div class="swiper-wrapper">
          <?php foreach ($available_movies as $movie): ?>
            <div class="swiper-slide">
              <a href="movie-details.php?id=<?php echo (int) $movie['id']; ?>" class="movie-link">
                <div class="box">
                  <img src="<?php echo htmlspecialchars($movie['image_url']); ?>"
                    alt="<?php echo htmlspecialchars($movie['title']); ?>" />
                  <div class="box-text">
                    <h2><?php echo htmlspecialchars($movie['title']); ?></h2>
                    <h3><?php echo htmlspecialchars($movie['genre'] ?? 'Unknown'); ?></h3>
                    <div class="rating-download">
                      <div class="rating">
                        <i class="bx bxs-star"></i>
                        <span><?php echo htmlspecialchars($movie['rating'] ?? '0'); ?></span>
                      </div>
                      <span class="box-btn"><i class="bx bx-arrow-to-right"></i></span>
                    </div>
                  </div>
                </div>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="swiper-pagination"></div>
      </div>
    <?php else: ?>
      <div class="no-movies">
        <p>No movies available right now.</p>
      </div>
    <?php endif; ?>
  </section>
  <section class="trending movies container" id="trending">
    <div class="heading">
      <i class="bx bxs-flame"></i>
      <h2>Coming Soon</h2>
    </div>

    <?php if (count($coming_soon_movies) > 0): ?>
      <div class="trending-content swiper">
        <div class="swiper-wrapper">
          <?php foreach ($coming_soon_movies as $movie): ?>
            <div class="swiper-slide">
              <a href="movie-details.php?id=<?php echo (int) $movie['id']; ?>" class="movie-link">
                <div class="box">
                  <img src="<?php echo htmlspecialchars($movie['image_url']); ?>"
                    alt="<?php echo htmlspecialchars($movie['title']); ?>" />
                  <div class="box-text">
                    <h2><?php echo htmlspecialchars($movie['title']); ?></h2>
                    <h3><?php echo htmlspecialchars($movie['genre'] ?? 'Unknown'); ?></h3>
                  </div>
                </div>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="swiper-pagination"></div>
      </div>
    <?php else: ?>
      <div class="no-movies">
        <p>No movies coming soon at the moment.</p>
      </div>
    <?php endif; ?>
  </section>

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
      fetch("backend/auth_status.php", { cache: "no-store" })
        .then((response) => response.json())
        .then((data) => {
          if (data.logged_in) {
            const displayName = data.name || data.email || "Profile";

            dropdownMenu.innerHTML = `
                <li><a href="profile.php" class="login-btn">Profile</a></li>
                ${Number(data.is_admin) === 1 ? '<li><a href="panel.php" class="login-btn">Control Panel</a></li>' : ''}
                <li><a href="logout.php" class="signup-btn">Logout</a></li>
              `;
          } else {
            dropdownMenu.innerHTML = `
                <li><a href="login.php" class="login-btn">Login</a></li>
                <li><a href="signup.php" class="signup-btn">Sign Up</a></li>
              `;
          }
        })
        .catch(() => {
          dropdownMenu.innerHTML = `
              <li><a href="login.php" class="login-btn">Login</a></li>
              <li><a href="signup.php" class="signup-btn">Sign Up</a></li>
            `;
        });
    }

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