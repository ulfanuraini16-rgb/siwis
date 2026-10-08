<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Laporan Wisudawan</title>
<style>
  @page { margin: 25pt; size: A4 landscape; }
  body {
    font-family: Helvetica, Arial, sans-serif;
    color: #14304a;
    margin: 0;
    padding: 0;
    font-size: 8pt;
  }
  .title {
    font-size: 13pt;
    font-weight: bold;
    color: #14304a;
    margin-bottom: 3pt;
  }
  .subtitle {
    font-size: 8pt;
    color: #6b7a89;
    margin-bottom: 12pt;
  }
  table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 5pt;
  }
  th {
    background-color: #14304a;
    color: #ffffff;
    font-weight: bold;
    font-size: 7.5pt;
    padding: 5pt 4pt;
    border: 0.5pt solid #14304a;
    text-align: left;
  }
  td {
    padding: 4pt 4pt;
    border: 0.5pt solid #cbd5e1;
    font-size: 7.5pt;
    vertical-align: middle;
  }
  tr:nth-child(even) td {
    background-color: #f8fafc;
  }
  .center {
    text-align: center;
  }
</style>
</head>
<body>
  <div class="title">{{ $kampus }} - Laporan Wisudawan</div>
  <div class="subtitle">{{ $keterangan }}</div>

  <table>
    <thead>
      <tr>
        <th class="center" style="width: 20pt;">No</th>
        <th style="width: 60pt;">NIM</th>
        <th style="width: 110pt;">Nama</th>
        <th style="width: 90pt;">Fakultas</th>
        <th style="width: 90pt;">Program Studi</th>
        <th class="center" style="width: 25pt;">IPK</th>
        <th style="width: 75pt;">Status</th>
        <th style="width: 70pt;">No Peserta</th>
        <th class="center" style="width: 35pt;">Sesi</th>
        <th class="center" style="width: 45pt;">Kehadiran</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $idx => $r)
        <tr>
          <td class="center">{{ $idx + 1 }}</td>
          <td>{{ $r->nim }}</td>
          <td>{{ $r->nama }}</td>
          <td>{{ $r->fakultas }}</td>
          <td>{{ $r->program_studi }}</td>
          <td class="center">{{ number_format($r->ipk, 2) }}</td>
          <td>{{ $r->status }}</td>
          <td>{{ $r->nomor_peserta ?? '-' }}</td>
          <td class="center">{{ $r->sesi ?? '-' }}</td>
          <td class="center">{{ $r->status_kehadiran ?? '-' }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="10" class="center" style="padding: 15pt;">Tidak ada data untuk filter yang dipilih.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</body>
</html>
