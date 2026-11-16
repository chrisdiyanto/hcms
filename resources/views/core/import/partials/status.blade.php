@php($label = ['validated' => ['Siap di-commit', 'info'], 'invalid' => ['Ada error', 'danger'], 'committed' => ['Tersimpan', 'success'], 'failed' => ['Gagal commit', 'danger']][$status] ?? [$status, 'secondary'])
<span class="badge bg-{{ $label[1] }}">{{ $label[0] }}</span>
