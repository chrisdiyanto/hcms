@extends('layouts.app')

@section('content')
@php
	$cnt = fn ($n) => \App\Support\Privacy\SmallGroups::count($n, $mask);
	$hide = fn ($n) => $mask && \App\Support\Privacy\SmallGroups::hidden($n);
	$num = fn ($v, $d = 2, $sign = false) => $v === null ? '—' : ($sign && $v > 0 ? '+' : '').number_format((float) $v, $d, ',', '.');
	$rp = fn ($v) => $v === null ? '—' : 'Rp '.number_format((float) $v, 0, ',', '.');
	$ibadge = ['continue' => 'bg-success', 'improve' => 'bg-warning text-dark', 'stop' => 'bg-danger', 'insufficient' => 'bg-secondary'];
@endphp
<div class="exec-dashboard">
<div class="page-titles d-flex justify-content-between align-items-center flex-wrap gap-2">
	<div><h4 class="mb-0">{{ $pageTitle }} {{ $year }}</h4><small class="text-muted">Pelaksanaan dan evaluasi empat tingkat per program, tanpa klaim sebab-akibat</small></div>
	<form method="GET" class="d-flex align-items-center gap-2"><label for="year" class="small text-muted mb-0">Tahun</label>
		<input id="year" type="number" name="year" value="{{ $year }}" class="form-control form-control-sm" style="width: 90px" onchange="this.form.submit()">
		<a href="{{ url('development/sessions?year='.$year) }}" class="btn btn-sm btn-outline-secondary text-nowrap">Jadwal pelatihan</a></form>
</div>

@include('executive._hero', [
	'kicker' => 'Peserta lulus', 'icon' => 'fa fa-certificate',
	'number' => $cnt($completed),
	'sub' => 'dari '.$cnt($participants).' peserta di '.$sessions.' kelas · biaya '.$rp($cost),
	'stats' => [
		['Reaksi (T1)', $l1 === null ? '—' : $num($l1, 2).' / 5', 'rata-rata survei peserta', 'fa fa-star'],
		['Pembelajaran (T2)', $num($l2, 1, true), 'rata-rata post − pre (poin)', 'fa fa-clipboard-check'],
		['Biaya per lulusan', $rp($costPerCompleted), 'biaya kelas ÷ peserta lulus', 'fa fa-money-bill-alt'],
	],
	'ring' => ['value' => $completion, 'center' => 'lulus', 'title' => 'Tingkat penyelesaian', 'icon' => 'fa fa-check-circle', 'slot' => 2,
		'note' => 'peserta lulus ÷ peserta (tanpa yang batal)',
		'chip' => 'Penerapan (T3) '.$num($l3, 2, true).' level · '.$cnt($l3n).' terukur'],
])

<div class="card exec-card"><div class="card-body">
	<div class="exec-section-header"><h5 class="exec-section-title"><span class="exec-icon-box exec-tone-primary" aria-hidden="true"><i class="fa fa-th-large"></i></span><span>Per program</span></h5></div>
	@if($rows->isEmpty())
		<div class="exec-empty-state"><p class="small mb-0">Belum ada kelas pelatihan pada tahun ini.</p></div>
	@else
		<div class="table-responsive"><table class="table exec-table mb-0">
			<thead><tr><th>Program</th><th class="text-end">Kelas</th><th class="text-end">Lulus / peserta</th><th class="text-end">Biaya/lulusan</th>
				<th class="text-end" title="Reaksi 1–5; tampil bila respons ≥ 50%">T1 reaksi</th><th class="text-end" title="Rata-rata post − pre">T2 belajar</th>
				<th class="text-end" title="Perubahan skor kompetensi sasaran 3–6 bulan setelah kelas">T3 penerapan</th><th class="text-end" title="Perubahan skor rating final periode berikutnya">T4 kinerja</th><th>Indikasi</th></tr></thead>
			<tbody>
			@foreach($rows as $r)
				<tr><td>{{ $r->name }}<div class="small text-muted">{{ $types[$r->type] ?? $r->type }}</div></td>
					<td class="text-end">{{ $r->sessions }}</td>
					<td class="text-end tabular-nums">{{ $cnt($r->completed) }} / {{ $cnt($r->participants) }}<div class="small text-muted">{{ $hide($r->participants) ? '—' : $num($r->completion, 0).'%' }}</div></td>
					<td class="text-end small">{{ $rp($r->cost_per_completed) }}</td>
					<td class="text-end">{{ $hide($r->l1_n) ? '—' : ($r->l1 === null ? '—' : $num($r->l1, 2)) }}<div class="small text-muted">respons {{ $num($r->l1_rate, 0) }}%</div></td>
					<td class="text-end">{{ $hide($r->l2_n) ? '—' : $num($r->l2, 1, true) }}<div class="small text-muted">n={{ $cnt($r->l2_n) }}</div></td>
					<td class="text-end">{{ $hide($r->l3_n) ? '—' : $num($r->l3, 2, true) }}<div class="small text-muted">n={{ $cnt($r->l3_n) }}</div></td>
					<td class="text-end">{{ $hide($r->l4_n) ? '—' : $num($r->l4, 2, true) }}<div class="small text-muted">n={{ $cnt($r->l4_n) }}</div></td>
					<td><span class="badge {{ $ibadge[$r->indication] }}">{{ $indications[$r->indication] }}</span></td></tr>
			@endforeach
			</tbody>
		</table></div>
	@endif
	<div class="exec-footnote"><i class="fa fa-info-circle" aria-hidden="true"></i><span>T1 dilaporkan bila respons ≥ 50% peserta hadir. T3 = skor kompetensi sasaran pada asesmen 3–6 bulan setelah kelas dibanding sebelum kelas. T4 = rating final periode berikutnya dibanding periode sebelum kelas. Indikasi: <em>pertimbangkan dihentikan</em> bila reaksi &lt; 3,0 atau rata-rata post − pre ≤ 0 (≥ 3 peserta teruji); <em>perbaiki</em> bila reaksi &lt; 3,5 atau penyelesaian &lt; 70%. Keputusan tetap pada pengelola program; perubahan skor tidak membuktikan sebab-akibat.</span></div>
</div></div>
@if($mask)<p class="small text-muted mt-2 mb-0"><i class="fa fa-user-secret" aria-hidden="true"></i> Kelompok berisi 1–4 orang ditampilkan "&lt; 5" untuk melindungi privasi pegawai.</p>@endif
</div>
@vite('resources/js/charts.js')
@endsection
