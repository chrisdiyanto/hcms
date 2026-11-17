{{-- Riwayat aspirasi karier (terbaru lebih dulu). Parameter: $history --}}
@php($horizons = \App\Domain\Talent\CareerAspirations::HORIZONS)
@php($mobility = \App\Domain\Talent\CareerAspirations::MOBILITY)
@forelse($history as $a)
	<div class="{{ $loop->first ? '' : 'border-top pt-2 mt-2' }}">
		<div class="d-flex justify-content-between align-items-start gap-2">
			<div class="fw-semibold">{{ $a->job_title ?? $a->target_role ?? $a->family_name }}</div>
			@if($a->is_current)<span class="badge bg-success">Terkini</span>@endif
		</div>
		<div class="small text-muted">
			{{ collect([$a->family_name, $a->job_title && $a->target_role ? $a->target_role : null])->filter()->implode(' · ') }}
			{{ $horizons[$a->horizon] ?? $a->horizon }} · {{ $mobility[$a->mobility] ?? $a->mobility }}
		</div>
		@if($a->interests)<div class="small mt-1"><span class="text-muted">Minat:</span> {{ $a->interests }}</div>@endif
		@if($a->development_needs)<div class="small"><span class="text-muted">Kebutuhan pengembangan:</span> {{ $a->development_needs }}</div>@endif
		<div class="small text-muted">Diisi {{ \Carbon\Carbon::parse($a->submitted_at)->translatedFormat('d M Y H:i') }}</div>
	</div>
@empty
	<p class="text-muted small mb-0">Belum ada aspirasi karier yang diisi.</p>
@endforelse
