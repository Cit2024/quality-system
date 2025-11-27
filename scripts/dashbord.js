/**
 * Dashboard Controller
 * Handles all chart initialization and interactions
 */
document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const pieChartElement = document.querySelector('.pie-chart');
    const lineChartElement = document.querySelector('.line-chart');
    const chartTypeSelect = document.getElementById('chart-type');
    const statisticsAboutSelect = document.getElementById('statistics-about');
    
    // First extract all chart data from DOM
    const chartData = {
        labels: JSON.parse(lineChartElement.dataset.semesterNames),
        datasets: {
            studentCounts: JSON.parse(lineChartElement.dataset.studentCounts),
            teacherRatings: JSON.parse(lineChartElement.dataset.teacherRatings),
            courseRatings: JSON.parse(lineChartElement.dataset.courseRatings)
        }
    };
    
    // Initialize charts after data is loaded
    const pieChart = initPieChart();
    let lineChart = initLineChart(chartData);
    
    // Event listeners
    chartTypeSelect.addEventListener('change', updateChartType);
    statisticsAboutSelect.addEventListener('change', updateChartData);

    /**
     * Initialize the student participation pie chart
     */
    function initPieChart() {
        return new Chart(document.getElementById('student-participation'), {
            type: 'pie',
            data: {
                labels: ["الطالبة المشاركين", "الطالبة الغير مشاكين"],
                datasets: [{
                    data: [
                        parseInt(pieChartElement.dataset.participatedStudents),
                        parseInt(pieChartElement.dataset.nonParticipatedStudents)
                    ],
                    backgroundColor: ["rgb(255, 99, 3)", "rgb(195, 195, 195)"],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                family: "DINRegular",
                                size: 14,
                            },
                        }
                    }
                }
            }
        });
    }

    /**
     * Initialize the main line chart
     * @param {object} data - Chart data object
     */
    function initLineChart(data) {
        return new Chart(document.getElementById('average-quarterly-ratings'), {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'مشاركة الطلبة في التقييم',
                    data: data.datasets.studentCounts,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 2,
                    tension: 0.1,
                    fill: true
                }]
            },
            options: getChartOptions('عدد الطلبة المشاركين', 'مشاركة الطلبة في التقييم')
        });
    }

    /**
     * Get chart options configuration
     * @param {string} yAxisTitle - Title for Y-axis
     * @param {string} chartTitle - Title for the chart
     */
    function getChartOptions(yAxisTitle, chartTitle) {
        return {
            responsive: true,
            plugins: {
                legend: {
                    labels: {
                        font: {
                            family: 'DINRegular',
                            size: 14
                        }
                    }
                },
                title: {
                    display: true,
                    text: chartTitle,
                    font: {
                        family: 'DINBold',
                        size: 16
                    },
                    padding: {
                        top: 10,
                        bottom: 20
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
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
                        text: 'الفصل الدراسي',
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
                }
            }
        };
    }

    /**
     * Update chart type based on user selection
     */
    function updateChartType() {
        
        // Save necessary data before destruction
        const oldLabels = [...lineChart.data.labels];
        const oldDataset = {...lineChart.data.datasets[0]};
        // Retrieve current titles from the existing options
        const yAxisTitle = lineChart.options.scales.y.title.text;
        const chartTitle = lineChart.options.plugins.title.text;
    
        // Destroy old chart
        lineChart.destroy();
        
        // Destroy old chart before creating new one
        lineChart.destroy();
        
        // Get current data and labels
        const currentDataset = lineChart.data.datasets[0];
        const currentOptions = lineChart.options;
        
        
        // Create new chart with updated type and regenerated options
        lineChart = new Chart(document.getElementById('average-quarterly-ratings'), {
            type: chartTypeSelect.value,
            data: {
                labels: oldLabels,
                datasets: [oldDataset]
            },
            options: getChartOptions(yAxisTitle, chartTitle)
        });
    }

    /**
     * Update chart data based on selected statistics
     */
    function updateChartData() {
        const selectedStat = statisticsAboutSelect.value;
        let newData, newLabel, newBackgroundColor, newBorderColor, yAxisTitle, chartTitle;

        switch(selectedStat) {
            case 'number-students':
                newData = chartData.datasets.studentCounts;
                newLabel = 'مشاركة الطلبة في التقييم';
                yAxisTitle = 'عدد الطلبة المشاركين';
                chartTitle = 'مشاركة الطلبة في التقييم';
                newBackgroundColor = 'rgba(75, 192, 192, 0.2)';
                newBorderColor = 'rgba(75, 192, 192, 1)';
                break;
                
            case 'average-teacher-ratings':
                newData = chartData.datasets.teacherRatings;
                newLabel = 'متوسط تقييمات المدرسين';
                yAxisTitle = 'متوسط التقييمات';
                chartTitle = 'متوسط تقييمات المدرسين في المواد';
                newBackgroundColor = 'rgba(255, 99, 132, 0.2)';
                newBorderColor = 'rgba(255, 99, 132, 1)';
                break;
                
            case 'average-course-ratings':
                newData = chartData.datasets.courseRatings;
                newLabel = 'متوسط تقييمات المقررات';
                yAxisTitle = 'متوسط التقييمات';
                chartTitle = 'متوسط تقييمات المقررات الدراسية';
                newBackgroundColor = 'rgba(54, 162, 235, 0.2)';
                newBorderColor = 'rgba(54, 162, 235, 1)';
                break;
        }

        // Update chart dataset
        lineChart.data.datasets[0] = {
            label: newLabel,
            data: newData,
            backgroundColor: newBackgroundColor,
            borderColor: newBorderColor,
            borderWidth: 2,
            tension: 0.1,
            fill: true
        };

        // Update chart options
        lineChart.options = getChartOptions(yAxisTitle, chartTitle);

        // Update chart
        lineChart.update();
    }

    // Update the window resize handler
    window.addEventListener('resize', function() {
        if (pieChart && !pieChart.destroyed) pieChart.resize();
        if (lineChart && !lineChart.destroyed) lineChart.resize();
    });
});