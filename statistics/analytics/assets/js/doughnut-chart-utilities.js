// doughnut-chart-utilities.js
/**
 * Doughnut Chart Utilities Module
 * Handles initialization and management of evaluation participation doughnut charts
 * @version 1.0.0
 */

const DoughnutChartManager = (() => {
  // ----------------------------
  // Configuration Constants
  // ----------------------------
  const CONFIG = {
    CHART: {
      DEFAULT_COLORS: {
        nonParticipant: '#cccccc',
        baseHue: 23, // Orange hue to match theme
        saturation: 100,
        lightness: 51
      },
      FONT: {
        family: 'DINRegular',
        size: 14
      },
      LEGEND: {
        position: 'bottom',
      },
      SELECTORS: {
        DOUGHNUT_CHART: '#department-breakdown-chart'
      }
    }
  };

  // ----------------------------
  // Core Chart Utilities
  // ----------------------------
  const chartRegistry = new Map();

  /**
   * Creates doughnut chart configuration
   * @param {Array} labels - Department names
   * @param {Array} data - Participation counts
   * @param {number} totalStudents - Total number of students
   */
  const createDoughnutConfig = (labels, data, totalStudents) => {
    // Generate dynamic colors for departments
    const departmentColors = labels.map((_, i) => {
      const hue = (CONFIG.CHART.DEFAULT_COLORS.baseHue + (i * 50)) % 360;
      return `hsl(${hue}, ${CONFIG.CHART.DEFAULT_COLORS.saturation}%, ${CONFIG.CHART.DEFAULT_COLORS.lightness}%)`;
    });

    return {
      type: 'doughnut',
      data: {
        labels: [...labels, 'لم يشارك'],
        datasets: [{
          data: [...data, totalStudents - data.reduce((a, b) => a + b, 0)],
          backgroundColor: [...departmentColors, CONFIG.CHART.DEFAULT_COLORS.nonParticipant],
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: CONFIG.CHART.LEGEND.position,
            labels: {
              ...CONFIG.CHART.LEGEND.labels,
              font: CONFIG.CHART.FONT,
              usePointStyle: true
            }
          },
          tooltip: {
            callbacks: {
              label: (context) => {
                const label = context.label || '';
                const value = context.raw || 0;
                const percentage = ((value / totalStudents) * 100).toFixed(1);
                return `${label}: ${value} (${percentage}%)`;
              }
            }
          }
        }
      }
    };
  };

  // ----------------------------
  // Chart Operations
  // ----------------------------

  /**
   * Initializes department breakdown chart
   * @param {HTMLElement} canvas - Chart canvas element
   */
  const initializeDepartmentChart = (canvas) => {
    if (!canvas) return;
    
    // Destroy existing chart if present
    const existingChart = Chart.getChart(canvas);
    if (existingChart) {
        existingChart.destroy();
    }

    // Parse dataset values
    const departmentStats = JSON.parse(canvas.dataset.departmentStats || '{}') || {};
    const totalStudents = Math.min(parseInt(canvas.dataset.totalStudents || 0), 100000);
    const nonParticipants = Math.min(parseInt(canvas.dataset.nonParticipants || 0), 100000);

    // Process data
    const labels = Object.keys(departmentStats);
    const data = Object.values(departmentStats);

    // Cleanup existing instance
    if (chartRegistry.has(canvas.id)) {
      chartRegistry.get(canvas.id).destroy();
    }

    // Create new chart
    const chart = new Chart(
      canvas.getContext('2d'),
      createDoughnutConfig(labels, data, totalStudents)
    );

    chartRegistry.set(canvas.id, chart);
  };

  // ----------------------------
  // Initialization Logic
  // ----------------------------

  /**
   * Initializes all doughnut charts on the page
   */
  const initDoughnutCharts = () => {
    try {
      const charts = document.querySelectorAll(CONFIG.CHART.SELECTORS.DOUGHNUT_CHART);
      charts.forEach(initializeDepartmentChart);
    } catch (error) {
      console.error('Doughnut chart initialization failed:', error);
    }
  };

  // ----------------------------
  // Public Interface
  // ----------------------------
  return {
    initialize: () => {
      document.addEventListener('DOMContentLoaded', initDoughnutCharts);
    },
    refreshChart: (canvasId) => {
      const canvas = document.getElementById(canvasId);
      if (canvas) initializeDepartmentChart(canvas);
    }
  };
})();

// Global initialization
window.DoughnutChartManager = DoughnutChartManager;
DoughnutChartManager.initialize();