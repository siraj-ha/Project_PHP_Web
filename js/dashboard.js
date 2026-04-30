const dashboardData = window.dashboardData || {
  genre: { labels: [], values: [] },
  status: { labels: [], values: [] },
  reservations: { labels: [], values: [], note: "" },
  ratings: { labels: [], values: [] },
};

function createChart(canvasId, config) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) {
    return;
  }

  const context = canvas.getContext("2d");
  new Chart(context, {
    options: {
      responsive: true,
      maintainAspectRatio: false,
      ...config.options,
    },
    ...config,
  });
}

function showChartNote(canvasId, note) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) {
    return;
  }

  const box = canvas.closest(".chart-box");
  if (!box) {
    return;
  }

  const message = document.createElement("p");
  message.className = "no-movies";
  message.style.marginTop = "12px";
  message.textContent = note;
  box.appendChild(message);
}

if (dashboardData.genre.labels.length > 0) {
  createChart("genreChart", {
    type: "pie",
    data: {
      labels: dashboardData.genre.labels,
      datasets: [
        {
          data: dashboardData.genre.values,
          backgroundColor: [
            "#f97316",
            "#22c55e",
            "#3b82f6",
            "#eab308",
            "#ef4444",
            "#8b5cf6",
            "#14b8a6",
            "#f43f5e",
          ],
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      radius: "50%",
      layout: {
        padding: 12,
      },
    },
  });
} else {
  showChartNote("genreChart", "No genre data yet.");
}

if (dashboardData.status.labels.length > 0) {
  createChart("statusChart", {
    type: "doughnut",
    data: {
      labels: dashboardData.status.labels,
      datasets: [
        {
          data: dashboardData.status.values,
          backgroundColor: ["#22c55e", "#f97316", "#64748b"],
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      radius: "50%",
      cutout: "72%",
      layout: {
        padding: 12,
      },
    },
  });
} else {
  showChartNote("statusChart", "No status data yet.");
}

if (dashboardData.reservations.labels.length > 0) {
  createChart("reservationChart", {
    type: "bar",
    data: {
      labels: dashboardData.reservations.labels,
      datasets: [
        {
          label: "Reserved Seats",
          data: dashboardData.reservations.values,
          backgroundColor: "#3b82f6",
        },
      ],
    },
    options: { scales: { y: { beginAtZero: true } } },
  });
} else {
  showChartNote(
    "reservationChart",
    dashboardData.reservations.note || "No reservations data yet.",
  );
}

if (dashboardData.ratings.labels.length > 0) {
  createChart("ratingChart", {
    type: "bar",
    data: {
      labels: dashboardData.ratings.labels,
      datasets: [
        {
          label: "Rating",
          data: dashboardData.ratings.values,
          backgroundColor: "#f97316",
        },
      ],
    },
    options: { scales: { y: { beginAtZero: true, max: 5 } } },
  });
} else {
  showChartNote("ratingChart", "No rating data yet.");
}

const deleteButtons = document.querySelectorAll(".delete-btn");

deleteButtons.forEach((btn) => {
  btn.addEventListener("click", function () {
    const row = btn.closest("tr");
    row.remove();
  });
});

const addBtn = document.querySelector(".add-btn");
if (addBtn) {
  addBtn.addEventListener("click", function () {
    alert("Add Reservation functionality coming soon!");
  });
}
