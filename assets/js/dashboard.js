/**
 * Dashboard Visualizations (Chart.js 4.x)
 * Renders:
 * 1. Monthly Expense Trend (Line/Area Chart)
 * 2. Income vs Expense Comparison (Grouped Bar Chart)
 * 3. Expense by Category (Doughnut Chart)
 */
document.addEventListener('DOMContentLoaded', () => {
    if (!window.DashboardData) return;

    const data = window.DashboardData;
    const fontPrimary = "'Plus Jakarta Sans', -apple-system, sans-serif";

    /**
     * Standard Indian Numbering System currency formatter (Lakhs, Crores)
     * e.g. 1000 -> ₹1,000 | 10000 -> ₹10,000 | 50000 -> ₹50,000 | 100000 -> ₹1,00,000
     * forceDecimals=true: 50000 -> ₹50,000.00 | 11600 -> ₹11,600.00
     */
    function formatIndianCurrency(amount, forceDecimals = false) {
        const num = Number(amount);
        if (isNaN(num)) return '₹0';
        const isNegative = num < 0;
        const absVal = Math.abs(num);
        const parts = absVal.toFixed(2).split('.');
        const intPart = parts[0];
        const decPart = parts[1] || '00';

        let formattedInt = '';
        if (intPart.length > 3) {
            const lastThree = intPart.substring(intPart.length - 3);
            let remaining = intPart.substring(0, intPart.length - 3);
            const chunks = [];
            while (remaining.length > 2) {
                chunks.push(remaining.substring(remaining.length - 2));
                remaining = remaining.substring(0, remaining.length - 2);
            }
            if (remaining.length > 0) {
                chunks.push(remaining);
            }
            formattedInt = chunks.reverse().join(',') + ',' + lastThree;
        } else {
            formattedInt = intPart;
        }

        let res = (isNegative ? '-' : '') + '₹' + formattedInt;
        if (forceDecimals || parseInt(decPart, 10) > 0) {
            res += '.' + decPart;
        }
        return res;
    }

    // ----------------------------------------------------------
    // 1. Monthly Expense Chart (Area / Line Trend)
    // ----------------------------------------------------------
    const expenseCtx = document.getElementById('monthlyExpenseChart');
    if (expenseCtx) {
        const hasExpenseData = data.expenseTrendLabels && data.expenseTrendLabels.length > 0;
        const labels = hasExpenseData ? data.expenseTrendLabels : ['Current Month'];
        const values = hasExpenseData ? data.expenseTrendAmounts : [0];

        new Chart(expenseCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Monthly Expense (₹)',
                    data: values,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.08)',
                    borderWidth: 2.5,
                    tension: 0.35,
                    fill: true,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#ef4444',
                    pointBorderWidth: 2.5,
                    pointRadius: 4.5,
                    pointHoverRadius: 6.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        display: false,
                        formatter: function(val) { return formatIndianCurrency(val, false); }
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleFont: { family: fontPrimary, weight: 700 },
                        bodyFont: { family: fontPrimary },
                        padding: 10,
                        callbacks: {
                            label: function(context) {
                                return ' Expenses: ' + formatIndianCurrency(context.parsed.y, true);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: fontPrimary, size: 11 }, color: '#64748b' }
                    },
                    y: {
                        border: { dash: [4, 4] },
                        grid: { color: '#f1f5f9' },
                        title: {
                            display: true,
                            text: 'Amount (₹)',
                            font: { family: fontPrimary, size: 11, weight: 600 },
                            color: '#64748b'
                        },
                        ticks: {
                            callback: function(val) { return formatIndianCurrency(val, false); },
                            font: { family: fontPrimary, size: 11 },
                            color: '#64748b'
                        }
                    }
                }
            }
        });
    }

    // ----------------------------------------------------------
    // 2. Income vs Expense Chart (Grouped Bar Chart)
    // ----------------------------------------------------------
    const cashFlowCtx = document.getElementById('cashFlowChart');
    if (cashFlowCtx) {
        const hasTrend = data.trendLabels && data.trendLabels.length > 0;
        const trendLabels = hasTrend ? data.trendLabels : ['No Data'];
        const trendIncome = hasTrend ? data.trendIncome : [0];
        const trendExpense = hasTrend ? data.trendExpense : [0];

        new Chart(cashFlowCtx, {
            type: 'bar',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        label: 'Income (₹)',
                        data: trendIncome,
                        backgroundColor: '#10b981',
                        borderRadius: 6,
                        barPercentage: 0.65,
                        categoryPercentage: 0.65
                    },
                    {
                        label: 'Expenses (₹)',
                        data: trendExpense,
                        backgroundColor: '#ef4444',
                        borderRadius: 6,
                        barPercentage: 0.65,
                        categoryPercentage: 0.65
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: {
                            font: { family: fontPrimary, weight: 600, size: 12 },
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 12
                        }
                    },
                    datalabels: {
                        display: false,
                        formatter: function(val) { return formatIndianCurrency(val, false); }
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleFont: { family: fontPrimary, weight: 700 },
                        bodyFont: { family: fontPrimary },
                        padding: 10,
                        callbacks: {
                            label: function(context) {
                                const rawLabel = context.dataset.label || '';
                                const cleanLabel = rawLabel.replace(/\s*\(₹\)/g, '');
                                return ' ' + cleanLabel + ': ' + formatIndianCurrency(context.parsed.y, true);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: fontPrimary, size: 11 }, color: '#64748b' }
                    },
                    y: {
                        border: { dash: [4, 4] },
                        grid: { color: '#f1f5f9' },
                        title: {
                            display: true,
                            text: 'Amount (₹)',
                            font: { family: fontPrimary, size: 11, weight: 600 },
                            color: '#64748b'
                        },
                        ticks: {
                            callback: function(val) { return formatIndianCurrency(val, false); },
                            font: { family: fontPrimary, size: 11 },
                            color: '#64748b'
                        }
                    }
                }
            }
        });
    }

    // ----------------------------------------------------------
    // 3. Expense by Category Doughnut Chart
    // ----------------------------------------------------------
    const categoryCtx = document.getElementById('categoryChart');
    if (categoryCtx) {
        const hasCategories = data.catAmounts && data.catAmounts.length > 0;
        
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: hasCategories ? data.catLabels : ['No Expenses Recorded'],
                datasets: [{
                    data: hasCategories ? data.catAmounts : [1],
                    backgroundColor: hasCategories ? data.catColors : ['#e2e8f0'],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '74%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: { family: fontPrimary, size: 11, weight: 600 },
                            padding: 12,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    datalabels: {
                        formatter: function(val) { return formatIndianCurrency(val, false); }
                    },
                    tooltip: {
                        enabled: hasCategories,
                        backgroundColor: '#0f172a',
                        titleFont: { family: fontPrimary, weight: 700 },
                        bodyFont: { family: fontPrimary },
                        padding: 10,
                        callbacks: {
                            label: function(context) {
                                return ' ' + context.label + ': ' + formatIndianCurrency(context.parsed, true);
                            }
                        }
                    }
                }
            }
        });
    }
});
