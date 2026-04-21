// Example Chart Data
const genreCtx = document.getElementById("genreChart").getContext("2d");
new Chart(genreCtx, {
  type: "pie",
  data: {
    labels: ["Anime", "Action", "Fantasy", "Drama", "Sci-Fi"],
    datasets: [
      {
        data: [5, 3, 2, 1, 1],
        backgroundColor: [
          "#f97316",
          "#22c55e",
          "#3b82f6",
          "#eab308",
          "#ef4444",
        ],
      },
    ],
  },
});

const statusCtx = document.getElementById("statusChart").getContext("2d");
new Chart(statusCtx, {
  type: "doughnut",
  data: {
    labels: ["Available", "Coming Soon"],
    datasets: [
      {
        data: [12, 6],
        backgroundColor: ["#22c55e", "#f97316"],
      },
    ],
  },
});

const reservationCtx = document
  .getElementById("reservationChart")
  .getContext("2d");
new Chart(reservationCtx, {
  type: "bar",
  data: {
    labels: [
      "Demon Slayer",
      "Interstellar",
      "One Piece: Red",
      "Jujutsu Kaisen",
      "Avatar",
    ],
    datasets: [
      {
        label: "Reserved Seats",
        data: [120, 250, 87, 0, 0],
        backgroundColor: "#3b82f6",
      },
    ],
  },
  options: { scales: { y: { beginAtZero: true } } },
});

const ratingCtx = document.getElementById("ratingChart").getContext("2d");
new Chart(ratingCtx, {
  type: "bar",
  data: {
    labels: [
      "Demon Slayer",
      "Interstellar",
      "One Piece: Red",
      "Jujutsu Kaisen",
      "Avatar",
    ],
    datasets: [
      {
        label: "Rating",
        data: [4.8, 4.3, 4.7, 4.5, 4.6],
        backgroundColor: "#f97316",
      },
    ],
  },
  options: { scales: { y: { beginAtZero: true, max: 5 } } },
});

const deleteButtons = document.querySelectorAll(".delete-btn");

deleteButtons.forEach((btn) => {
  btn.addEventListener("click", function () {
    const row = btn.closest("tr");
    row.remove();
  });
});

// Optional: Add Reservation (placeholder example)
const addBtn = document.querySelector(".add-btn");
addBtn.addEventListener("click", function () {
  alert("Add Reservation functionality coming soon!");
});
