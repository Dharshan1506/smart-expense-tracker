/**
 * Analytics Visualizations (Chart.js)
 * Daily Area Chart & Monthly Line/Bar Cashflow Comparison
 */
document.addEventListener('DOMContentLoaded', () => {
    if (!window.AnalyticsData) return;

    const data = window.AnalyticsData;
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

    // 1. Daily Expenses Line / Area Chart
    const dailyCtx = document.getElementById('dailyExpenseChart');
    if (dailyCtx) {
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: data.dailyLabels.length ? data.dailyLabels : ['No Data'],
                datasets: [{
                    label: 'Daily Spending (₹)',
                    data: data.dailySpent.length ? data.dailySpent : [0],
                    borderColor: '#06b6d4',
                    backgroundColor: 'rgba(6, 182, 212, 0.12)',
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#06b6d4',
                    pointRadius: 4,
                    pointHoverRadius: 6
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
                            label: function(ctx) {
                                return ' Spent: ' + formatIndianCurrency(ctx.parsed.y, true);
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

    // 2. Monthly Trend Chart (Income vs Expense)
    const monthlyCtx = document.getElementById('monthlyTrendChart');
    if (monthlyCtx) {
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: data.monthlyLabels.length ? data.monthlyLabels : ['No Data'],
                datasets: [
                    {
                        label: 'Income (₹)',
                        data: data.monthlyIncome.length ? data.monthlyIncome : [0],
                        backgroundColor: '#10b981',
                        borderRadius: 6
                    },
                    {
                        label: 'Expenses (₹)',
                        data: data.monthlyExpense.length ? data.monthlyExpense : [0],
                        backgroundColor: '#ef4444',
                        borderRadius: 6
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
                            label: function(ctx) {
                                const rawLabel = ctx.dataset.label || '';
                                const cleanLabel = rawLabel.replace(/\s*\(₹\)/g, '');
                                return ' ' + cleanLabel + ': ' + formatIndianCurrency(ctx.parsed.y, true);
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
});
