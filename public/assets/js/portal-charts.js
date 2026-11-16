(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.PortalCharts = api;
})(typeof window === 'undefined' ? globalThis : window, function () {
    function trend(labels, values, futureLabels, futureValues) {
        const categories = [...labels, ...futureLabels];
        return {
            categories,
            series: [
                { name: 'Actual requests', data: [...values, ...futureLabels.map(() => null)] },
                { name: 'Forecast', data: [...labels.map((_, i) => i === labels.length - 1 ? values[i] : null), ...futureValues] },
            ],
        };
    }

    function mount(data) {
        const dark = () => document.documentElement.dataset.bsTheme === 'dark';
        const text = () => 'var(--tblr-body-color, #475569)';
        const charts = [];
        const common = {
            chart: { fontFamily: 'inherit', height: 300, toolbar: { show: false }, animations: { enabled: !window.matchMedia('(prefers-reduced-motion: reduce)').matches, speed: 500, dynamicAnimation: { enabled: false } }, parentHeightOffset: 0 },
            theme: { mode: dark() ? 'dark' : 'light' },
            colors: [getComputedStyle(document.documentElement).getPropertyValue('--portal-primary').trim() || '#293681', getComputedStyle(document.documentElement).getPropertyValue('--portal-secondary').trim() || '#4274D9', getComputedStyle(document.documentElement).getPropertyValue('--portal-tertiary').trim() || '#95CCDD', getComputedStyle(document.documentElement).getPropertyValue('--portal-quaternary').trim() || '#D0E7E6'],
            grid: { borderColor: 'rgba(98,105,118,.13)', strokeDashArray: 3 },
            dataLabels: { enabled: false },
            legend: { fontSize: '12px', position: 'bottom', labels: { colors: text() } },
            tooltip: { theme: dark() ? 'dark' : 'light' },
            noData: { text: 'No request data yet', style: { color: text() } },
        };
        const render = (id, options) => {
            const element = document.getElementById(id);
            if (!element) return null;
            const chart = new ApexCharts(element, { ...common, ...options, chart: { ...common.chart, ...options.chart } });
            chart.render(); charts.push(chart); return chart;
        };
        const initial = trend(data.labels, data.values, data.futureLabels, data.futureValues);
        const monthly = render('monthlyLineChart', {
            chart: { type: 'area', height: 320 }, series: initial.series,
            stroke: { width: [2, 2], curve: 'straight', dashArray: [0, 5] },
            fill: { type: 'gradient', gradient: { opacityFrom: .18, opacityTo: .01, stops: [0, 100] } },
            markers: { size: 3, hover: { size: 5 } },
            xaxis: { categories: initial.categories, labels: { style: { colors: text(), fontSize: '11px' }, rotate: -35, hideOverlappingLabels: true }, axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis: { min: 0, forceNiceScale: true, labels: { formatter: value => Math.round(value).toLocaleString(), style: { colors: text() } } },
            tooltip: { theme: dark() ? 'dark' : 'light', shared: true, intersect: false, y: { formatter: value => value == null ? '' : value.toLocaleString() + ' requests' } },
        });
        render('categoryDoughnut', {
            chart: { type: 'donut', height: 290 }, series: data.categoryValues,
            labels: ['Academic', 'Certification'], stroke: { width: 3, colors: ['var(--tblr-bg-surface, #fff)'] },
            plotOptions: { pie: { donut: { size: '76%', labels: { show: true, name: { fontSize: '12px' }, value: { fontSize: '28px' }, total: { show: true, showAlways: true, label: 'All requests', formatter: () => data.categoryValues.reduce((a, b) => a + b, 0).toLocaleString() } } } } },
            legend: { show: false },
        });
        render('demandBarChart', {
            chart: { type: 'bar', height: Math.max(250, data.demandLabels.length * 50) },
            series: [{ name: 'Requests', data: data.demandValues }],
            plotOptions: { bar: { horizontal: true, barHeight: '38%', borderRadius: 3 } },
            xaxis: { categories: data.demandLabels, labels: { formatter: value => Math.round(value).toLocaleString(), style: { colors: text() } }, axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis: { labels: { maxWidth: 220, style: { colors: text(), fontSize: '11px' } } },
            fill: { opacity: 1 },
            tooltip: { theme: dark() ? 'dark' : 'light', y: { formatter: value => value.toLocaleString() + ' requests' } },
        });
        new MutationObserver(() => charts.forEach(chart => chart.updateOptions({
            theme: { mode: dark() ? 'dark' : 'light' }, tooltip: { theme: dark() ? 'dark' : 'light' },
        }, false, false)))
            .observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });
        return {
            updateForecast(labels, values) {
                if (!monthly) return;
                const next = trend(data.labels, data.values, labels, values);
                monthly.updateOptions({ xaxis: { categories: next.categories }, series: next.series }, false, false);
            },
        };
    }
    return { trend, mount };
});
