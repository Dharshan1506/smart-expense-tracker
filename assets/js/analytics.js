/**
 * Analytics Visualizations (Chart.js)
 * Modern Midnight Dark Theme: Daily Area Chart & Monthly Cashflow Comparison
 */
document.addEventListener('DOMContentLoaded', () => {
    if (!window.AnalyticsData) return;

    const data = window.AnalyticsData;
    const fontPrimary = "'Plus Jakarta Sans', -apple-system, sans-serif";
    const fontMono = "'JetBrains Mono', monospace";

    /**
     * Standard Indian Numbering System currency formatter (Lakhs, Crores)
     * e.g. 1000 -> ₹1,000 | 10000 -> ₹10,000 | 50000 -> ₹50,000 | 100000 -> ₹1,00,000
     */
    function formatIndianCurrency(amount, forceDecimals = true) {
        const num = Number(amount);
        if (isNaN(num)) return '₹0.00';
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
        const ctx2d = dailyCtx.getContext('2d');
        let cyanGradient = 'rgba(6, 182, 212, 0.15)';
        if (ctx2d) {
            const gradient = ctx2d.createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, 'rgba(6, 182, 212, 0.35)');
            gradient.addColorStop(1, 'rgba(6, 182, 212, 0.00)');
            cyanGradient = gradient;
        }

        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: data.dailyLabels.length ? data.dailyLabels : ['No Activity'],
                datasets: [{
                    label: 'Daily Spending (₹)',
                    data: data.dailySpent.length ? data.dailySpent : [0],
                    borderColor: '#06b6d4',
                    backgroundColor: cyanGradient,
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.38,
                    pointBackgroundColor: '#06b6d4',
                    pointBorderColor: '#080c16',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(14, 21, 38, 0.95)',
                        borderColor: 'rgba(255, 255, 255, 0.12)',
                        borderWidth: 1,
                        titleFont: { family: fontPrimary, weight: 700, size: 12 },
                        bodyFont: { family: fontMono, size: 12 },
                        titleColor: '#ffffff',
                        bodyColor: '#38bdf8',
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function(ctx) {
                                return 'Spent: ' + formatIndianCurrency(ctx.parsed.y, true);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: fontPrimary, size: 11 }, color: '#94a3b8' }
                    },
                    y: {
                        border: { dash: [4, 4] },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        title: {
                            display: true,
                            text: 'Amount (₹)',
                            font: { family: fontPrimary, size: 11, weight: 600 },
                            color: '#64748b'
                        },
                        ticks: {
                            callback: function(val) { return formatIndianCurrency(val, false); },
                            font: { family: fontMono, size: 11 },
                            color: '#94a3b8'
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
                labels: data.monthlyLabels.length ? data.monthlyLabels : ['No Activity'],
                datasets: [
                    {
                        label: 'Income',
                        data: data.monthlyIncome.length ? data.monthlyIncome : [0],
                        backgroundColor: '#10b981',
                        borderRadius: 6,
                        barPercentage: 0.65,
                        categoryPercentage: 0.7
                    },
                    {
                        label: 'Expenses',
                        data: data.monthlyExpense.length ? data.monthlyExpense : [0],
                        backgroundColor: '#f43f5e',
                        borderRadius: 6,
                        barPercentage: 0.65,
                        categoryPercentage: 0.7
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
                            color: '#94a3b8',
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 14
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(14, 21, 38, 0.95)',
                        borderColor: 'rgba(255, 255, 255, 0.12)',
                        borderWidth: 1,
                        titleFont: { family: fontPrimary, weight: 700, size: 12 },
                        bodyFont: { family: fontMono, size: 12 },
                        titleColor: '#ffffff',
                        bodyColor: '#e2e8f0',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(ctx) {
                                const rawLabel = ctx.dataset.label || '';
                                return ' ' + rawLabel + ': ' + formatIndianCurrency(ctx.parsed.y, true);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: fontPrimary, size: 11 }, color: '#94a3b8' }
                    },
                    y: {
                        border: { dash: [4, 4] },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        title: {
                            display: true,
                            text: 'Amount (₹)',
                            font: { family: fontPrimary, size: 11, weight: 600 },
                            color: '#64748b'
                        },
                        ticks: {
                            callback: function(val) { return formatIndianCurrency(val, false); },
                            font: { family: fontMono, size: 11 },
                            color: '#94a3b8'
                        }
                    }
                }
            }
        });
    }
});
