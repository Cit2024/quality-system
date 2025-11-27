// chart-utilities.js
/**
 * Enhanced Chart Utilities Module with Title Support
 * @version 1.3.0
 */

const ChartManager = (() => {
  // ----------------------------
  // Configuration Constants
  // ----------------------------
  const CONFIG = {
    CHART: {
      PRIMARY_COLOR: "rgb(255, 99, 3)",
      SECONDARY_COLOR: "rgb(195, 195, 195)",
      TRANSPARENT_PRIMARY: "rgba(255, 99, 3, 0.2)",
      FONT: {
        family: "DINRegular",
        size: 14
      },
      TITLE_FONT: {
        family: "DINBold",
        size: 16
      },
      SCALES: {
        Y_MAX: 5,
        Y_MIN: 0
      }
    },
    SELECTORS: {
      HISTORY_CHART: '#evaluation-chart',
      CHART_TYPE: '.chart-type-selector'
    }
  };

  // ----------------------------
  // Core Chart Utilities
  // ----------------------------
  const chartRegistry = new Map();

  /**
   * Creates chart configuration with dynamic titles
   * @param {string} type - Chart type
   * @param {Array} labels - X-axis labels
   * @param {Array} data - Y-axis data
   * @param {string} xTitle - X-axis title
   * @param {string} yTitle - Y-axis title
   * @param {string} mainTitle - Chart main title
   */
  const createChartConfig = (type, labels, data, xTitle, yTitle, mainTitle) => ({
    type,
    data: {
      labels,
      datasets: [{
        label: "المتوسط التقييمات",
        data,
        fill: false,
        backgroundColor: CONFIG.CHART.TRANSPARENT_PRIMARY,
        borderColor: CONFIG.CHART.PRIMARY_COLOR,
        tension: 0.1,
        borderWidth: type === 'bar' ? 2 : 1
      }]
    },
    options: {
      scales: {
        x: {
          title: {
            display: !!xTitle,
            text: xTitle,
            font: CONFIG.CHART.FONT
          },
          grid: { color: "rgba(0, 0, 0, 0.1)" },
          ticks: { font: CONFIG.CHART.FONT }
        },
        y: {
          title: {
            display: !!yTitle,
            text: yTitle,
            font: CONFIG.CHART.FONT
          },
          min: CONFIG.CHART.SCALES.Y_MIN,
          max: CONFIG.CHART.SCALES.Y_MAX,
          beginAtZero: true,
          grid: { color: "rgba(0, 0, 0, 0.1)" },
          ticks: { font: CONFIG.CHART.FONT }
        }
      },
      plugins: {
        title: {
          display: !!mainTitle,
          text: mainTitle,
          font: CONFIG.CHART.TITLE_FONT,
          position: 'top'
        },
        legend: { labels: { font: CONFIG.CHART.FONT } }
      }
    }
  });

  // ----------------------------
  // Chart Rendering Operations
  // ----------------------------

  /**
   * Renders chart with titles from dataset
   * @param {string} canvasId - Canvas element ID
   * @param {Array} labels - Time labels
   * @param {Array} averages - Rating averages
   * @param {string} type - Chart type
   * @param {string} xTitle - X-axis title
   * @param {string} yTitle - Y-axis title
   * @param {string} mainTitle - Main chart title
   */
  const renderHistoryChart = (canvasId, labels, averages, type = 'line', xTitle, yTitle, mainTitle) => {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    // Cleanup existing instance
    if (chartRegistry.has(canvasId)) {
      chartRegistry.get(canvasId).destroy();
    }

    // Create new chart with titles
    const chart = new Chart(
      ctx,
      createChartConfig(type, labels, averages, xTitle, yTitle, mainTitle)
    );

    chartRegistry.set(canvasId, chart);
  };

  /**
   * Handles chart type changes while preserving titles
   */
  const updateChartType = (canvasId, newType) => {
    const chart = chartRegistry.get(canvasId);
    const ctx = document.getElementById(canvasId);
    if (!chart || !ctx) return;

    // Get current titles from dataset
    const xTitle = ctx.dataset.xTitle;
    const yTitle = ctx.dataset.yTitle;
    const mainTitle = ctx.dataset.mainTitle;

    chart.destroy();
    renderHistoryChart(
      canvasId,
      chart.data.labels,
      chart.data.datasets[0].data,
      newType,
      xTitle,
      yTitle,
      mainTitle
    );
  };

  // ----------------------------
  // Initialization Logic
  // ----------------------------

  /**
   * Initializes chart with data-* attributes
   */
  const initEvaluationChart = () => {
    try {
      const ctx = document.querySelector(CONFIG.SELECTORS.HISTORY_CHART);
      if (!ctx) return;

      // Parse dataset values
      const labels = JSON.parse(ctx.dataset.labels || '[]');
      const averages = JSON.parse(ctx.dataset.averages || '[]')
        .map(avg => parseFloat(avg).toFixed(1));

      // Get titles from data attributes
      const xTitle = ctx.dataset.xTitle;
      const yTitle = ctx.dataset.yTitle;
      const mainTitle = ctx.dataset.mainTitle;

      renderHistoryChart(
        CONFIG.SELECTORS.HISTORY_CHART.slice(1),
        labels,
        averages,
        'line',
        xTitle,
        yTitle,
        mainTitle
      );

      // Add chart type controls
      document.querySelector(CONFIG.SELECTORS.CHART_TYPE)
        ?.addEventListener('change', (e) => {
          updateChartType(
            CONFIG.SELECTORS.HISTORY_CHART.slice(1),
            e.target.value
          );
        });

    } catch (error) {
      console.error('Chart initialization failed:', error);
    }
  };

  // ----------------------------
  // Public Interface
  // ----------------------------
  return {
    initialize: () => {
      document.addEventListener('DOMContentLoaded', initEvaluationChart);
    },
    renderChart: renderHistoryChart,
    updateChartType
  };
})();

// Global initialization
window.ChartManager = ChartManager;
ChartManager.initialize();