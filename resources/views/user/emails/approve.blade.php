<!DOCTYPE html>
<html lang="en-US">
	<head>
		<meta charset="utf-8">
	</head>
	<body>
        <h2>{{ $judul }}</h2>
        <p>Informasi Ini bersifat pribadi & tidak untuk disebarluaskan</p>

        <p>Jenis Laporan : Register Risiko/Monitoring Risiko</p>
        <p>Nama Risiko : {{ $risiko }}</p>
        <p>Divisi : {{ $divisi }}</p> 
        <p>Perihal :  {{ $perihal }}</p>
        <p>Catatan : {{ $catatan }}</p>

        <p>{{ date('Y-m-d H:i:s') }}</p>
		
		{{ config('sximo.cnf_appname') }} 
	</body>
</html>