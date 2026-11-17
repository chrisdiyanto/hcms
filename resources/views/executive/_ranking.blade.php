{{-- Peringkat: batang horizontal N teratas + tabel lengkap yang bisa dicari.
     Parameter: $title, $icon, $tone, $rows (label, value), $name, $slot, $column, $empty, $footnote?, $top? --}}
@php
    $rows = collect($rows)->values();
    $top = $rows->take($top ?? 10);
    $sum = max(1, (int) $rows->sum('value'));
@endphp
<div class="card exec-card h-100 mb-0"><div class="card-body d-flex flex-column">
    <div class="exec-section-header">
        <h5 class="exec-section-title"><span class="exec-icon-box {{ $tone }}" aria-hidden="true"><i class="{{ $icon }}"></i></span><span>{{ $title }}</span></h5>
        @if($rows->count() > $top->count())
            <span class="exec-pill exec-tone-neutral tabular-nums">{{ $top->count() }} dari {{ number_format($rows->count(), 0, ',', '.') }}</span>
        @endif
    </div>
    @if($rows->isEmpty())
        <div class="exec-empty-state"><p class="small mb-0">{{ $empty }}</p></div>
    @else
        <div data-chart="{{ json_encode(['type' => 'hbar', 'name' => $name, 'slot' => $slot, 'labels' => $top->pluck('label')->all(), 'values' => $top->pluck('value')->all(),
            'neutral' => $top->keys()->filter(fn ($i) => $top[$i]['label'] === 'Belum diisi')->values()->all()]) }}"
            style="height: {{ 34 * $top->count() + 16 }}px" role="img"
            aria-label="{{ $title }}: {{ $top->map(fn ($r) => $r['label'].' '.$r['value'])->implode(', ') }}"></div>
        @if($rows->count() > $top->count())
            <details class="exec-details mt-3">
                <summary>Lihat semua ({{ number_format($rows->count(), 0, ',', '.') }})</summary>
                <input class="form-control form-control-sm exec-unit-search my-2" type="search" data-unit-filter placeholder="Cari..." autocomplete="off" aria-label="Cari {{ mb_strtolower($column) }}">
                <div class="table-responsive exec-table-scroll"><table class="table exec-table mb-0">
                    <thead><tr><th>{{ $column }}</th><th class="text-end">{{ $name }}</th><th class="text-end">Porsi</th></tr></thead>
                    <tbody>
                    @foreach($rows as $row)
                        <tr data-unit-row data-unit-name="{{ mb_strtolower($row['label']) }}">
                            <td>{{ $row['label'] }}</td><td class="text-end tabular-nums">{{ number_format($row['value'], 0, ',', '.') }}</td>
                            <td class="text-end tabular-nums text-muted">{{ number_format($row['value'] / $sum * 100, 1, ',', '.') }}%</td>
                        </tr>
                    @endforeach
                    <tr class="exec-filter-empty"><td colspan="3" class="text-center text-muted small py-3">Tidak ada yang cocok.</td></tr>
                    </tbody>
                </table></div>
            </details>
        @endif
    @endif
    @if(!empty($footnote))
        <div class="exec-footnote mt-auto"><i class="fa fa-info-circle" aria-hidden="true"></i><span>{{ $footnote }}</span></div>
    @endif
</div></div>
