{{-- Kartu "Sorotan": kalimat otomatis dari data. Parameter: $insights ([ikon, label, teks]) --}}
<div class="card exec-card h-100 mb-0"><div class="card-body">
    <div class="exec-section-header">
        <h5 class="exec-section-title"><span class="exec-icon-box exec-tone-warning" aria-hidden="true"><i class="fa fa-lightbulb"></i></span><span>Sorotan</span></h5>
    </div>
    @if(empty($insights))
        <div class="exec-empty-state"><p class="small mb-0">Belum ada data yang cukup untuk disorot.</p></div>
    @else
        <ul class="exec-insights">
            @foreach($insights as [$icon, $label, $text])
                <li><span class="exec-insight-icon" aria-hidden="true"><i class="{{ $icon }}"></i></span>
                    <div><div class="exec-insight-label">{{ $label }}</div><div class="exec-insight-text">{{ $text }}</div></div></li>
            @endforeach
        </ul>
    @endif
</div></div>
