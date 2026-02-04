/**
 * Teacher Statistics Controller
 * Handles teacher-specific statistics
 */
class TeacherStatisticsController {
  constructor() {
    this.teacherId = window.TEACHER_ID;
    this.currentType = "teacher_evaluation"; // Default view
    this.filters = { search: "", filter: "all" };
    this.initElements();
    this.initEventListeners();
    this.loadInitialData();
  }

  initElements() {
    this.elements = {
      searchInput: document.querySelector(".search-input"),
      filterSelect: document.querySelector(".filter-select"),
      cardsContainer: document.querySelector(".cards-container"),
      subTabButtons: document.querySelectorAll(".sub-tab-button"),
    };
  }

  initEventListeners() {
    // Sub tabs
    this.elements.subTabButtons.forEach((btn) => {
      btn.addEventListener("click", (e) => {
        const newType = e.currentTarget.dataset.type;
        if (this.currentType !== newType) {
          this.currentType = newType;
          this.filters.filter = "all";
          this.elements.filterSelect.value = "all";
          this.updateSubTabs();
          this.loadData();
        }
      });
    });

    // Search
    let searchTimeout;
    this.elements.searchInput.addEventListener("input", (e) => {
      clearTimeout(searchTimeout);
      this.filters.search = e.target.value.toLowerCase();
      searchTimeout = setTimeout(() => this.filterCards(), 300);
    });

    // Filter
    this.elements.filterSelect.addEventListener("change", (e) => {
      this.filters.filter = e.target.value;
      this.loadData();
    });
  }

  updateSubTabs() {
    this.elements.subTabButtons.forEach((btn) => {
      btn.classList.toggle("active", btn.dataset.type === this.currentType);
    });
  }

  async loadData() {
    const loader = createLoadingSpinnerBar();

    try {
      this.elements.cardsContainer.innerHTML = "";
      this.elements.cardsContainer.appendChild(loader);

      const params = new URLSearchParams({
        teacher_id: this.teacherId,
        type: this.currentType,
        filter: this.filters.filter,
      });

      const response = await fetch(
        `./statistics/get_teacher_statistics.php?${params}`
      );
      const { html, success, error } = await response.json();

      if (!success) throw new Error(error);

      this.elements.cardsContainer.innerHTML = html;
      this.updateCharts();

      // Reapply search filter if active
      if (this.filters.search) {
        this.filterCards();
        this.toggleNoResults(
          this.elements.cardsContainer.children.length === 0
        );
      }
    } catch (error) {
      showErrorToast(`فشل التحميل: ${error.message}`);
      console.error("Loading failed:", error);
    } finally {
      removeLoadingSpinnerBar(loader);
    }
  }

  filterCards() {
    const searchTerm = this.filters.search;
    const cards = this.elements.cardsContainer.querySelectorAll(
      ".course-card, .card"
    );
    let visibleCount = 0;

    cards.forEach((card) => {
      const titleElement = card.querySelector(".course-info h3, .course-title");
      const title = titleElement?.textContent?.toLowerCase() || "";
      const isMatch = title.includes(searchTerm);

      card.style.display = isMatch ? "block" : "none";
      if (isMatch) visibleCount++;
    });

    this.toggleNoResults(visibleCount === 0);
  }

  toggleNoResults(show) {
    const message = document.querySelector(".no-results-message");
    if (!message) return;

    message.style.display = show ? "flex" : "none";
    if (show)
      message.querySelector(".search-term").textContent = this.filters.search;
  }

  updateCharts() {
    document.querySelectorAll(".progress-bar").forEach((bar) => {
      const percent = bar.parentElement.dataset.percent;
      bar.style.width = `${percent}%`;
    });
  }

  loadInitialData() {
    this.updateSubTabs();
    this.loadData();
  }
}
// Chart Initialization
function initializeCharts() {
  document
    .querySelectorAll('canvas[id^="chart-course-"], canvas[id^="chart-teacher-"]')
    .forEach((canvas) => {
      const evaluations = parseFloat(canvas.dataset.evaluations) || 0;
      const total = parseFloat(canvas.dataset.totalStudents) || 0;

      // Destroy existing chart if any
      const existingChart = Chart.getChart(canvas);
      if (existingChart) {
        existingChart.destroy();
      }

      canvas.chart = new Chart(canvas, {
        type: "doughnut",
        data: {
          labels: ["المشتركين", "الغير مشتركين"],
          datasets: [
            {
              data: [evaluations, total - evaluations],
              backgroundColor: ["rgb(255, 99, 3)", "rgba(204, 204, 204, 1)"],
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: "bottom",
              labels: {
                font: {
                  family: "DINRegular",
                  size: 14,
                },
                usePointStyle: true,
              },
            },
          },
        },
      });
    });
}
// Initialize
document.addEventListener("DOMContentLoaded", () => {
  window.statisticsController = new TeacherStatisticsController();
  this.elements.cardsContainer.innerHTML = html;
  initializeCharts(); // Initialize the charts after loading new data
});
