// Grafik HCMS (ECharts 6, tree-shaken). Dimuat hanya di halaman laporan lewat @vite('resources/js/charts.js').
// Elemen: <div data-chart='{"type":"heatmap"|"radar"|"bar"|"donut"|"area"|"flows"|"hbar"|"columns"|"stack100"|"ring", ...}'></div>
// — data disiapkan server (JSON). Warna kategorikal dari palet tervalidasi (CVD-aman, mode terang & gelap);
// teks/sumbu/garis memakai token tema Sximo sehingga ikut berubah saat mode gelap di-toggle.
import * as echarts from 'echarts/core';
import { HeatmapChart, RadarChart, BarChart, PieChart, LineChart } from 'echarts/charts';
import { GridComponent, TooltipComponent, VisualMapComponent, LegendComponent, RadarComponent } from 'echarts/components';
import { SVGRenderer } from 'echarts/renderers';

echarts.use([HeatmapChart, RadarChart, BarChart, PieChart, LineChart, GridComponent, TooltipComponent, VisualMapComponent, LegendComponent, RadarComponent, SVGRenderer]);

const fmt = (v) => (v === null || v === undefined || Number.isNaN(v) ? '—' : Number(v).toLocaleString('id-ID', { maximumFractionDigits: 2 }));
const unit = (c) => (v) => fmt(v) + (c.suffix ?? ''); // satuan opsional, mis. "%"

// Palet kategorikal (urutan tetap, divalidasi validate_palette.js terhadap permukaan kartu tema)
const PALETTE = {
    light: ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4'],
    dark: ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181'],
};
const NEUTRAL = { light: '#a3a29b', dark: '#6c6b65' }; // "Belum diisi" / sisa: abu-abu, bukan warna seri

function theme() {
    const dark = document.body.classList.contains('dark-theme') || document.documentElement.dataset.bsTheme === 'dark';
    const css = getComputedStyle(document.body);
    const v = (name, fallback) => css.getPropertyValue(name).trim() || fallback;
    return {
        dark,
        series: dark ? PALETTE.dark : PALETTE.light,
        neutral: dark ? NEUTRAL.dark : NEUTRAL.light,
        text: v('--sx-title', dark ? '#f0efec' : '#1f1d2b'),
        muted: v('--sx-muted', dark ? '#a8a6b8' : '#6b6880'),
        line: v('--sx-line', dark ? '#2c2a3a' : '#ebe9f3'),
        surface: v('--sx-surface', dark ? '#1a1924' : '#ffffff'),
    };
}

const tooltip = (t, extra = {}) => ({
    backgroundColor: t.surface, borderColor: t.line, textStyle: { color: t.text, fontSize: 12 },
    extraCssText: 'box-shadow:0 6px 18px rgba(0,0,0,.12);border-radius:10px;', ...extra,
});
const axisCommon = (t) => ({
    axisLine: { lineStyle: { color: t.line } }, axisTick: { show: false },
    axisLabel: { color: t.muted, fontSize: 11 }, splitLine: { lineStyle: { color: t.line, type: [3, 3] } },
});

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

    // Komposisi (gender, status kerja): donut tipis, celah 2px warna permukaan, total di tengah.
    // Legenda HTML di view memuat nama + angka (identitas tidak bergantung warna saja).
    donut: (c, t) => ({
        tooltip: tooltip(t, { trigger: 'item', formatter: (p) => `${p.marker} ${p.name}: <b>${fmt(p.value)}</b> (${fmt(p.percent)}%)` }),
        series: [{
            type: 'pie', radius: ['64%', '86%'], center: ['50%', '50%'], avoidLabelOverlap: false, label: { show: false },
            itemStyle: { borderColor: t.surface, borderWidth: 2, borderRadius: 4 },
            emphasis: { scale: true, scaleSize: 4 },
            data: c.items.map((it, i) => ({ name: it.label, value: it.value, itemStyle: { color: it.neutral ? t.neutral : t.series[i % t.series.length] } })),
        }],
        graphic: undefined,
    }),

    // Tren headcount 12 bulan: satu seri, garis 2px + area lembut, titik akhir diberi label nilai
    area: (c, t) => ({
        tooltip: tooltip(t, { trigger: 'axis', axisPointer: { type: 'line', lineStyle: { color: t.muted, type: [4, 4] } },
            formatter: (ps) => `${ps[0].axisValue}<br>${ps[0].marker} ${c.name}: <b>${unit(c)(ps[0].value)}</b>` }),
        grid: { left: 8, right: 48, top: 16, bottom: 4, containLabel: true },
        xAxis: { type: 'category', data: c.labels, boundaryGap: false, ...axisCommon(t), splitLine: { show: false } },
        yAxis: { type: 'value', minInterval: c.suffix ? 0 : 1, scale: !c.zero, ...axisCommon(t), axisLabel: { color: t.muted, fontSize: 11, formatter: unit(c) } },
        series: [{
            type: 'line', name: c.name, data: c.values, smooth: 0.25, symbol: 'circle', symbolSize: 8, showSymbol: false,
            lineStyle: { width: 2, color: t.series[0] }, itemStyle: { color: t.series[0], borderColor: t.surface, borderWidth: 2 },
            areaStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                { offset: 0, color: t.series[0] + (t.dark ? '55' : '33') }, { offset: 1, color: t.series[0] + '00' }]) },
            endLabel: { show: true, color: t.text, fontWeight: 600, formatter: (p) => unit(c)(p.value) },
        }],
    }),

    // Arus pegawai: dua seri (baru vs keluar) berdampingan, satu sumbu
    flows: (c, t) => ({
        tooltip: tooltip(t, { trigger: 'axis', axisPointer: { type: 'shadow', shadowStyle: { color: t.line + '66' } } }),
        legend: { top: 0, right: 0, itemWidth: 10, itemHeight: 10, icon: 'roundRect', textStyle: { color: t.muted, fontSize: 12 } },
        grid: { left: 8, right: 8, top: 30, bottom: 4, containLabel: true },
        xAxis: { type: 'category', data: c.labels, ...axisCommon(t), splitLine: { show: false } },
        yAxis: { type: 'value', minInterval: 1, ...axisCommon(t) },
        series: c.series.map((s, i) => ({
            type: 'bar', name: s.name, data: s.values, barMaxWidth: 14, barGap: '20%',
            itemStyle: { color: t.series[s.slot ?? i], borderRadius: [4, 4, 0, 0] },
            emphasis: { focus: 'series' },
        })),
    }),

    // Peringkat (unit): batang horizontal satu warna, nilai di ujung batang
    hbar: (c, t) => ({
        tooltip: tooltip(t, { trigger: 'axis', axisPointer: { type: 'shadow', shadowStyle: { color: t.line + '66' } },
            formatter: (ps) => `${ps[0].name}<br>${ps[0].marker} ${c.name}: <b>${unit(c)(ps[0].value)}</b>` }),
        grid: { left: 8, right: 48, top: 4, bottom: 4, containLabel: true },
        xAxis: { type: 'value', minInterval: 1, show: false },
        yAxis: { type: 'category', data: c.labels, inverse: true, axisLine: { show: false }, axisTick: { show: false },
            axisLabel: { color: t.text, fontSize: 12, width: 150, overflow: 'truncate' } },
        series: [{
            type: 'bar', name: c.name, barMaxWidth: 16, barCategoryGap: '35%',
            data: c.values.map((v, i) => ({
                value: v,
                itemStyle: { color: c.colors?.[i] ?? (c.neutral?.includes(i) ? t.neutral : t.series[c.slot ?? 0]) },
            })),
            itemStyle: { color: t.series[c.slot ?? 0], borderRadius: [0, 4, 4, 0] },
            showBackground: true, backgroundStyle: { color: t.line + '55', borderRadius: [0, 4, 4, 0] },
            label: { show: true, position: 'right', color: t.text, fontWeight: 600, formatter: (p) => unit(c)(p.value) },
        }],
    }),

    // Sebaran berurutan (kelompok usia, kenaikan grade per bulan): kolom satu warna, nilai di atas;
    // item ber-flag neutral ("Belum diisi") memakai abu-abu, bukan warna seri
    columns: (c, t) => ({
        tooltip: tooltip(t, { trigger: 'axis', axisPointer: { type: 'shadow', shadowStyle: { color: t.line + '66' } },
            formatter: (ps) => `${ps[0].name}<br>${ps[0].marker} ${c.name}: <b>${unit(c)(ps[0].value)}</b>` }),
        grid: { left: 8, right: 8, top: 22, bottom: 4, containLabel: true },
        xAxis: { type: 'category', data: c.labels, ...axisCommon(t), splitLine: { show: false }, axisLabel: { color: t.muted, fontSize: 11, interval: 0, hideOverlap: true } },
        yAxis: { type: 'value', minInterval: 1, ...axisCommon(t), axisLabel: { show: false } },
        series: [{
            type: 'bar', name: c.name, barMaxWidth: c.barMaxWidth ?? 44, barCategoryGap: '30%',
            data: c.values.map((v, i) => ({ value: v, itemStyle: { color: c.neutral?.includes(i) ? t.neutral : t.series[c.slot ?? 0] } })),
            itemStyle: { borderRadius: [4, 4, 0, 0] },
            label: { show: c.labels.length <= 12, position: 'top', color: t.text, fontWeight: 600, fontSize: 11, formatter: (p) => (p.value ? unit(c)(p.value) : '') },
        }],
    }),

    // Representasi per tahap (DEIB): batang 100% bertumpuk per tahap; persen di dalam segmen yang cukup lebar,
    // jumlah absolut di tooltip. Celah 2px warna permukaan antarsegmen.
    stack100: (c, t) => {
        const totals = c.stages.map((_, i) => c.series.reduce((sum, s) => sum + (s.values[i] || 0), 0));
        const share = (v, i) => (totals[i] ? (v / totals[i]) * 100 : 0);
        return {
            tooltip: tooltip(t, { trigger: 'axis', axisPointer: { type: 'shadow', shadowStyle: { color: t.line + '66' } },
                formatter: (ps) => `${ps[0].name} (${fmt(totals[ps[0].dataIndex])})<br>` + ps.map((p) => `${p.marker} ${p.seriesName}: <b>${fmt(c.series[p.seriesIndex].values[p.dataIndex])}</b> (${fmt(p.value)}%)`).join('<br>') }),
            legend: { top: 0, left: 0, itemWidth: 10, itemHeight: 10, icon: 'roundRect', textStyle: { color: t.muted, fontSize: 12 } },
            grid: { left: 8, right: 8, top: 32, bottom: 4, containLabel: true },
            xAxis: { type: 'value', max: 100, show: false },
            yAxis: { type: 'category', data: c.stages.map((s, i) => `${s} · ${fmt(totals[i])}`), inverse: true, axisLine: { show: false }, axisTick: { show: false },
                axisLabel: { color: t.text, fontSize: 12 } },
            series: c.series.map((s, k) => ({
                type: 'bar', name: s.name, stack: 'total', barWidth: 26,
                data: s.values.map((v, i) => Math.round(share(v, i) * 10) / 10),
                itemStyle: { color: s.neutral ? t.neutral : t.series[s.slot ?? k], borderColor: t.surface, borderWidth: 2, borderRadius: 4 },
                label: { show: true, color: '#fff', fontWeight: 600, fontSize: 11, formatter: (p) => (!s.neutral && p.value >= 9 ? `${fmt(p.value)}%` : '') },
                emphasis: { focus: 'series' },
            })),
        };
    },

    // Satu persentase (keterisian posisi): cincin progres, sisa berwarna garis tema
    ring: (c, t) => ({
        tooltip: { show: false },
        series: [{
            type: 'pie', radius: ['72%', '90%'], silent: true, label: { show: false }, startAngle: 90,
            itemStyle: { borderRadius: 4 },
            data: [
                { value: c.value, itemStyle: { color: t.series[c.slot ?? 2] } },
                { value: Math.max(0, 100 - c.value), itemStyle: { color: t.line } },
            ],
        }],
    }),
};

const mounted = [];

function render(el) {
    const config = JSON.parse(el.dataset.chart);
    const chart = echarts.init(el, null, { renderer: 'svg' });
    chart.setOption(builders[config.type](config, theme()));
    new ResizeObserver(() => chart.resize()).observe(el);
    mounted.push({ chart, config });
}

document.querySelectorAll('[data-chart]').forEach(render);

// Toggle mode gelap Sximo mengganti kelas body: render ulang dengan palet & token mode yang baru
new MutationObserver(() => {
    const t = theme();
    mounted.forEach(({ chart, config }) => chart.setOption(builders[config.type](config, t), true));
}).observe(document.body, { attributes: true, attributeFilter: ['class'] });
