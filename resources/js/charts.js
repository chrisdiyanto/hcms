// Grafik HCMS (ECharts 6, tree-shaken). Dimuat hanya di halaman laporan lewat @vite('resources/js/charts.js').
// Elemen: <div data-chart='{"type":"heatmap"|"radar"|"bar", ...}'></div> — data disiapkan server (JSON).
import * as echarts from 'echarts/core';
import { HeatmapChart, RadarChart, BarChart } from 'echarts/charts';
import { GridComponent, TooltipComponent, VisualMapComponent, LegendComponent, RadarComponent } from 'echarts/components';
import { SVGRenderer } from 'echarts/renderers';

echarts.use([HeatmapChart, RadarChart, BarChart, GridComponent, TooltipComponent, VisualMapComponent, LegendComponent, RadarComponent, SVGRenderer]);

const fmt = (v) => (v === null || v === undefined || Number.isNaN(v) ? '—' : Number(v).toLocaleString('id-ID', { maximumFractionDigits: 2 }));

const builders = {
    // gap rata-rata (aktual − wajib) per unit × kompetensi; merah = kurang, hijau = memenuhi
    heatmap: (c) => ({
        tooltip: {
            formatter: (p) => `${c.rows[p.value[1]]}<br>${c.cols[p.value[0]]}: gap <b>${fmt(p.value[2])}</b><br>${p.value[3]} pegawai`,
        },
        grid: { left: 8, right: 8, top: 8, bottom: 60, containLabel: true },
        xAxis: { type: 'category', data: c.cols, splitArea: { show: true }, axisLabel: { rotate: 45, fontSize: 11 } },
        yAxis: { type: 'category', data: c.rows, splitArea: { show: true }, axisLabel: { fontSize: 11 } },
        visualMap: {
            min: -2, max: 1, calculable: true, orient: 'horizontal', left: 'center', bottom: 0, itemHeight: 160,
            inRange: { color: ['#b42318', '#f79009', '#fef0c7', '#d1fadf', '#12b76a'] }, text: ['memenuhi', 'gap'],
        },
        series: [{ type: 'heatmap', data: c.data, label: { show: true, fontSize: 11, formatter: (p) => fmt(p.value[2]) } }],
    }),
    radar: (c) => ({
        tooltip: {},
        legend: { bottom: 0, data: ['Wajib', 'Aktual'] },
        radar: { indicator: c.indicators.map((name) => ({ name, max: 5 })), radius: '62%' },
        series: [{
            type: 'radar',
            data: [
                { name: 'Wajib', value: c.required, lineStyle: { type: 'dashed' }, itemStyle: { color: '#667085' } },
                { name: 'Aktual', value: c.actual, areaStyle: { opacity: 0.2 }, itemStyle: { color: '#1570ef' } },
            ],
        }],
    }),
    bar: (c) => ({
        tooltip: { trigger: 'axis' },
        grid: { left: 8, right: 16, top: 8, bottom: 8, containLabel: true },
        xAxis: { type: 'value', minInterval: 1 },
        yAxis: { type: 'category', data: c.labels, inverse: true },
        series: [{ type: 'bar', data: c.values.map((v, i) => ({ value: v, itemStyle: { color: c.colors?.[i] ?? '#1570ef' } })), barMaxWidth: 22, label: { show: true, position: 'right' } }],
    }),
};

function render(el) {
    const config = JSON.parse(el.dataset.chart);
    const chart = echarts.init(el, null, { renderer: 'svg' });
    chart.setOption(builders[config.type](config));
    new ResizeObserver(() => chart.resize()).observe(el);
}

document.querySelectorAll('[data-chart]').forEach(render);
