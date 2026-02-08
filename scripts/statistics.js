/**
 * Statistics Page Controller
 * Handles filtering, display, and URL-based tab navigation
 */
class StatisticsController {
  constructor() {
    this.currentTarget = new URLSearchParams(window.location.search).get('target') || 'student';
    this.currentType = this.getFirstValidType();
    this.filters = { search: '', filter: 'all' };
    this.initElements();
    this.initEventListeners();
    this.loadInitialData();
  }

  getFirstValidType() {
    return Object.keys(FORM_TYPES).find(type =>
      FORM_TYPES[type].allowed_targets.includes(this.currentTarget)
    );
  }

  initElements() {
    this.elements = {
      mainTabs: document.querySelectorAll('.tab-button'),
      searchInput: document.querySelector('.search-input'),
      filterSelect: document.querySelector('.filter-select'),
      cardsContainers: document.querySelectorAll('.cards-container')
    };
  }

  initEventListeners() {
    // Main tabs
    this.elements.mainTabs.forEach(btn => {
      btn.addEventListener('click', (e) => {
        this.currentTarget = e.currentTarget.dataset.target;
        this.currentType = this.getFirstValidType();
        this.filters.filter = 'all';
        this.elements.filterSelect.value = 'all';
        this.updateUI();
        this.updateURL();
        this.loadData();
      });
    });

    // Sub tabs
    document.addEventListener('click', (e) => {
      if (e.target.closest('.sub-tab-button')) {
        const newType = e.target.closest('.sub-tab-button').dataset.type;
        if (this.currentType !== newType) {
          this.currentType = newType;
          this.filters.filter = 'all';
          this.elements.filterSelect.value = 'all';
          this.updateSubTabs();
          this.loadData();
        }
      }
    });

    // Search
    let searchTimeout;
    this.elements.searchInput.addEventListener('input', (e) => {
      clearTimeout(searchTimeout);
      this.filters.search = e.target.value.toLowerCase();
      searchTimeout = setTimeout(() => this.filterCards(), 300);
    });

    // Filter
    this.elements.filterSelect.addEventListener('change', (e) => {
      this.filters.filter = e.target.value;
      this.loadData();
    });
  }

  updateUI() {
    // Main tabs
    this.elements.mainTabs.forEach(btn =>
      btn.classList.toggle('active', btn.dataset.target === this.currentTarget)
    );

    // Tab contents
    document.querySelectorAll('.tab-content').forEach(content =>
      content.classList.toggle('active', content.id === `${this.currentTarget}-tab`)
    );

    // Sub tabs
    this.updateSubTabs();
  }

  updateSubTabs() {
    // First hide all sub-tabs
    document.querySelectorAll('.sub-tabs').forEach(subTabContainer => {
      subTabContainer.style.display = 'none';
    });

    // Then show only valid ones
    const activeTabContent = document.querySelector(`#${this.currentTarget}-tab`);
    const activeSubTabs = activeTabContent.querySelector('.sub-tabs');

    if (activeSubTabs) {
      activeSubTabs.style.display = 'flex';

      // Update individual buttons
      activeSubTabs.querySelectorAll('.sub-tab-button').forEach(btn => {
        const isValid = window.FORM_TYPES[btn.dataset.type].allowed_targets.includes(this.currentTarget);
        btn.style.display = isValid ? 'flex' : 'none';
        btn.classList.toggle('active', btn.dataset.type === this.currentType);
      });
    }
  }


  async loadData() {
    const loader = createLoadingSpinnerBar();
    const container = document.querySelector(`#${this.currentTarget}-tab .cards-container`);

    try {
      container.innerHTML = '';
      container.appendChild(loader);

      const params = new URLSearchParams({
        target: this.currentTarget,
        type: this.currentType,
        filter: this.filters.filter
      });

      const response = await fetch(`./statistics/get_statistics.php?${params}`);
      const { html, filters, success, error } = await response.json();

      if (!success) throw new Error(error);

      container.innerHTML = html;
      initializeCharts();
      this.updateFilters(filters);

      // Reapply search filter if active
      if (this.filters.search) {
        this.filterCards();
        this.toggleNoResults(container.children.length === 0);
      }

    } catch (error) {
      showErrorToast(`فشل التحميل: ${error.message}`);
      console.error('Loading failed:', error);
    } finally {
      removeLoadingSpinnerBar(loader);
    }
  }

  filterCards() {
    const container = document.querySelector(`#${this.currentTarget}-tab .cards-container`);
    if (!container) return;

    const searchTerm = this.filters.search;
    const cards = container.querySelectorAll('.card');
    let visibleCount = 0;

    cards.forEach(card => {
      const titleElement = card.querySelector(
        '.course-title, .card-info-teacher-course h3, .program-title'
      );
      const title = titleElement?.textContent?.toLowerCase() || '';
      const isMatch = title.includes(searchTerm);

      card.style.display = isMatch ? 'block' : 'none';
      if (isMatch) visibleCount++;
    });

    this.toggleNoResults(visibleCount === 0);
  }

  toggleNoResults(show) {
    const message = document.querySelector(`#${this.currentTarget}-tab .no-results-message`);
    if (!message) return;

    message.style.display = show ? 'flex' : 'none';
    if (show) message.querySelector('.search-term').textContent = this.filters.search;
  }

  updateFilters(filters) {
    this.elements.filterSelect.innerHTML = '<option value="all">الكل</option>';

    if (typeof filters === 'object' && !Array.isArray(filters)) {
      // Handle time-based filters (alumni program evaluations)
      Object.entries(filters).forEach(([value, text]) => {
        const option = new Option(text, value);
        this.elements.filterSelect.add(option);
      });
    } else if (Array.isArray(filters)) {
      // Handle semester filters (courses/teachers)
      filters.forEach(value => {
        const option = new Option(value.text, value.id);
        this.elements.filterSelect.add(option);
      });
    }
  }

  updateURL() {
    const url = new URL(window.location);
    url.searchParams.set('target', this.currentTarget);
    window.history.pushState({}, '', url);
  }

  loadInitialData() {
    this.updateUI();
    this.loadData();
  }
}

// Chart Initialization
function initializeCharts() {
  document.querySelectorAll('canvas[id^="chart-number-evaluation"]').forEach(canvas => {
    if (canvas.chart) return; // Prevent reinitialization

    const evaluations = parseFloat(canvas.dataset.evaluations) || 0;
    const total = parseFloat(canvas.dataset.totalStudents) || 0;
    const nonParticipants = Math.max(0, total - evaluations);

    // Primary color: #FF6303, Gray: #e0e0e0 or similar for non-participants
    const primaryColor = '#FF6303';
    const grayColor = '#e0e0e0';

    canvas.chart = new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels: ['المشتركين', 'غير المشتركين'],
        datasets: [{
          data: [evaluations, nonParticipants],
          backgroundColor: [
            primaryColor,
            grayColor
          ],
          borderWidth: 0,
          hoverOffset: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%', // Make it look like a ring
        plugins: {
          legend: {
            display: false // Hide legend to save space, numbers below explain it
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                const label = context.label || '';
                const value = context.raw || 0;
                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                return `${label}: ${value} (${percentage}%)`;
              }
            }
          }
        },
      },
    });
  });
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
  window.statisticsController = new StatisticsController();
  initializeCharts();
});

// In sub-tab click event listener
document.addEventListener('click', (e) => {
  if (e.target.closest('.sub-tab-button')) {
    const newType = e.target.closest('.sub-tab-button').dataset.type;
    if (this.currentType !== newType) {
      this.currentType = newType;
      // Reset filter to 'all'
      this.filters.filter = 'all';
      this.elements.filterSelect.value = 'all';
      this.updateSubTabs();
      this.loadData();
    }
  }
});