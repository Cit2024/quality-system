// teacher_dashboard.js
function initializeCharts(
  participationData,
  semesterNames,
  studentCounts,
  avgRatings
) {
  // Participation chart
  const participationCtx = document
    .getElementById("participation-chart")
    .getContext("2d");
  new Chart(participationCtx, {
    type: "pie",
    data: {
      labels: ["مشارك", "غير مشارك"],
      datasets: [
        {
          data: participationData,
          backgroundColor: ["rgb(255, 99, 3)", "rgb(195, 195, 195)"],
          borderWidth: 1,
        },
      ],
    },
    options: {
      responsive: true,
      plugins: {
        legend: {
          position: "bottom",
          labels: {
            font: {
              family: "'DINRegular', sans-serif",
              size: 14,
            },
          },
        },
      },
    },
  });

  // History chart
  const historyCtx = document.getElementById("history-chart").getContext("2d");
  let historyChart = new Chart(historyCtx, {
    type: "line",
    data: {
      labels: semesterNames,
      datasets: [
        {
          label: "عدد الطلاب",
          data: studentCounts,
          backgroundColor: 'rgba(75, 192, 192, 0.2)',
          borderColor: 'rgba(75, 192, 192, 1)',
          borderWidth: 2,
          tension: 0.2,
          fill: true,
        },
      ],
    },
    options: {
      responsive: true,
      plugins: {
        title: {
          display: true,
          text: "عدد الطلاب الذين قاموا بالتقييم",
          font: {
            family: "DINBold",
            size: 16,
          },
          padding: {
            top: 10,
            bottom: 20,
          },
        },
        legend: {
          labels: {
            font: {
              family: "'DINRegular', sans-serif",
            },
          },
        },
      },
      scales: {
        y: {
          beginAtZero: true,
          title: {
            display: true,
            text: "عدد الطلاب",
            font: {
              family: "'DINRegular', sans-serif",
              size: 14,
            },
          },
          ticks: {
            font: {
              family: "'DINRegular', sans-serif",
            },
          },
        },
        x: {
          title: {
            display: true,
            text: "الفصل الدراسي",
            font: {
              family: "'DINRegular', sans-serif",
              size: 14,
            },
          },
          ticks: {
            font: {
              family: "'DINRegular', sans-serif",
            },
          },
        },
      },
    },
  });

  // Update chart based on selections
  document.getElementById("chart-type").addEventListener("change", updateChart);
  document.getElementById("data-type").addEventListener("change", updateChart);

  function updateChart() {
    const chartType = document.getElementById("chart-type").value;
    const dataType = document.getElementById("data-type").value;
    
    // Define titles based on data type
    let chartTitle, yAxisTitle;
    if (dataType === "students") {
      chartTitle = "عدد الطلاب الذين قاموا بالتقييم";
      yAxisTitle = "عدد الطلاب";
    } else {
      chartTitle = "متوسط التقييم عبر الفصول";
      yAxisTitle = "متوسط التقييم (من 5)";
    }

    historyChart.destroy();

    historyChart = new Chart(historyCtx, {
      type: chartType,
      data: {
        labels: semesterNames,
        datasets: [
          {
            label: dataType === "students" ? "عدد الطلاب" : "متوسط التقييم",
            data: dataType === "students" ? studentCounts : avgRatings,
            borderColor: dataType === "students" ? "#3498db" : "#2ecc71",
            backgroundColor:
              dataType === "students"
                ? "rgba(52, 152, 219, 0.1)"
                : "rgba(46, 204, 113, 0.1)",
            tension: 0.2,
            fill: true,
          },
        ],
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            labels: {
              font: {
                family: "DINRegular",
                size: 14,
              },
            },
          },
          title: {
            display: true,
            text: chartTitle,
            font: {
              family: "DINBold",
              size: 16,
            },
            padding: {
              top: 10,
              bottom: 20,
            },
          },
        },
        scales: {
          y: {
            beginAtZero: dataType === "students",
            title: {
              display: true,
              text: yAxisTitle,
              font: {
                family: "DINRegular",
                size: 14,
              },
            },
            ticks: {
              font: {
                family: "DINRegular",
                size: 14,
              },
            },
          },
          x: {
            title: {
              display: true,
              text: "الفصل الدراسي",
              font: {
                family: "DINRegular",
                size: 14,
              },
            },
            ticks: {
              font: {
                family: "DINRegular",
                size: 14,
              },
            },
          },
        },
      },
    });
  }
}
