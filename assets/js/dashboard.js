/**
 * Dashboard Visualizations (Chart.js 4.x) & Micro-Interactions
 * Renders:
 * 1. Animated Number Counters
 * 2. Monthly Expense Trend (Smooth Area Gradient)
 * 3. Income vs Expense Comparison (Grouped Modern Bar)
 * 4. Expense by Category (Doughnut with Glass Accents)
 */
document.addEventListener('DOMContentLoaded', () => {
    // ----------------------------------------------------------
    // 1. Subtle Animated Number Counters
    // ----------------------------------------------------------
    function animateCounters() {
        const counters = document.querySelectorAll('.animate-counter');
        counters.forEach(counter => {
            const target = parseFloat(counter.getAttribute('data-target') || '0');
            const isCurrency = counter.getAttribute('data-currency') === 'true';
            const duration = 1200; // ms
            const startTime = performance.now();

            function updateCounter(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                // Ease-out cubic
                const easeProgress = 1 - Math.pow(1 - progress, 3);
                const currentVal = target * easeProgress;

                if (isCurrency) {
                    counter.textContent = formatIndianCurrency(currentVal, true);
                } else {
                    counter.textContent = Math.round(currentVal).toString();
                }

                if (progress < 1) {
                    requestAnimationFrame(updateCounter);
                } else {
                    if (isCurrency) {
                        counter.textContent = formatIndianCurrency(target, true);
                    }
                }
            }

            requestAnimationFrame(updateCounter);
        });
    }

    animateCounters();

    if (!window.DashboardData) return;

    const data = window.DashboardData;
    const fontPrimary = "'Plus Jakarta Sans', -apple-system, sans-serif";
    const fontMono = "'JetBrains Mono', monospace";

    /**
     * Standard Indian Numbering System currency formatter (Lakhs, Crores)
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

    // ----------------------------------------------------------
    // 2. Monthly Expense Chart (Area / Line Trend)
    // ----------------------------------------------------------
    const expenseCtx = document.getElementById('monthlyExpenseChart');
    if (expenseCtx) {
        const hasExpenseData = data.expenseTrendLabels && data.expenseTrendLabels.length > 0;
        const labels = hasExpenseData ? data.expenseTrendLabels : ['Current Month'];
        const values = hasExpenseData ? data.expenseTrendAmounts : [0];

        const ctx = expenseCtx.getContext('2d');
        const gradientFill = ctx.createLinearGradient(0, 0, 0, 260);
        gradientFill.addColorStop(0, 'rgba(244, 63, 94, 0.35)');
        gradientFill.addColorStop(1, 'rgba(244, 63, 94, 0.0)');

        new Chart(expenseCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Monthly Expense (₹)',
                    data: values,
                    borderColor: '#f43f5e',
                    backgroundColor: gradientFill,
                    borderWidth: 3,
                    tension: 0.38,
                    fill: true,
                    pointBackgroundColor: '#0e1526',
                    pointBorderColor: '#fb7185',
                    pointBorderWidth: 2.5,
                    pointRadius: 4.5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#090d16',
                        borderColor: 'rgba(244, 63, 94, 0.35)',
                        borderWidth: 1,
                        titleColor: '#ffffff',
                        bodyColor: '#cbd5e1',
                        titleFont: { family: fontPrimary, weight: 700, size: 12 },
                        bodyFont: { family: fontMono, size: 12 },
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                return ' Outflow: ' + formatIndianCurrency(context.parsed.y, true);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { font: { family: fontPrimary, size: 11 }, color: '#94a3b8' }
                    },
                    y: {
                        border: { dash: [4, 4] },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
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

    // ----------------------------------------------------------
    // 3. Income vs Expense Chart (Grouped Bar Chart)
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
                        backgroundColor: 'rgba(59, 130, 246, 0.85)',
                        hoverBackgroundColor: '#3b82f6',
                        borderRadius: 6,
                        barPercentage: 0.65,
                        categoryPercentage: 0.65
                    },
                    {
                        label: 'Expenses (₹)',
                        data: trendExpense,
                        backgroundColor: 'rgba(244, 63, 94, 0.85)',
                        hoverBackgroundColor: '#f43f5e',
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
                            color: '#cbd5e1',
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 12
                        }
                    },
                    tooltip: {
                        backgroundColor: '#090d16',
                        borderColor: 'rgba(255, 255, 255, 0.12)',
                        borderWidth: 1,
                        titleColor: '#ffffff',
                        bodyColor: '#cbd5e1',
                        titleFont: { family: fontPrimary, weight: 700, size: 12 },
                        bodyFont: { family: fontMono, size: 12 },
                        padding: 12,
                        cornerRadius: 8,
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
                        ticks: { font: { family: fontPrimary, size: 11 }, color: '#94a3b8' }
                    },
                    y: {
                        border: { dash: [4, 4] },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
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

    // ----------------------------------------------------------
    // 4. Expense by Category Doughnut Chart
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
                    backgroundColor: hasCategories ? data.catColors : ['rgba(255,255,255,0.08)'],
                    borderWidth: 2,
                    borderColor: '#0e1526',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: { family: fontPrimary, size: 11, weight: 600 },
                            color: '#94a3b8',
                            padding: 12,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        enabled: hasCategories,
                        backgroundColor: '#090d16',
                        borderColor: 'rgba(255, 255, 255, 0.12)',
                        borderWidth: 1,
                        titleColor: '#ffffff',
                        bodyColor: '#cbd5e1',
                        titleFont: { family: fontPrimary, weight: 700 },
                        bodyFont: { family: fontMono },
                        padding: 12,
                        cornerRadius: 8,
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
