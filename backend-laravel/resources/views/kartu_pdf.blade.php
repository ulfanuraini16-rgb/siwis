<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Kartu Peserta Wisuda</title>
<style>
  @page { margin: 0; size: 420pt 600pt; }
  body {
    font-family: Helvetica, Arial, sans-serif;
    margin: 0;
    padding: 0;
    color: #14304a;
    background-color: #ffffff;
  }
  .header {
    background-color: #14304a;
    color: #ffffff;
    height: 110pt;
    padding: 20pt 25pt;
    box-sizing: border-box;
    position: relative;
  }
  .logo {
    width: 44pt;
    height: 44pt;
    border-radius: 50%;
    border: 2pt solid #c8963e;
    display: inline-block;
    vertical-align: middle;
    text-align: center;
    line-height: 44pt;
    font-weight: bold;
    font-size: 20pt;
    color: #c8963e;
    margin-right: 15pt;
  }
  .header-info {
    display: inline-block;
    vertical-align: middle;
  }
  .kampus-title {
    font-size: 14pt;
    font-weight: bold;
    color: #ffffff;
    margin-bottom: 3pt;
  }
  .card-type {
    font-size: 10pt;
    color: #c8963e;
    letter-spacing: 1px;
    margin-bottom: 2pt;
  }
  .periode-name {
    font-size: 9pt;
    color: #cbd5e1;
  }
  .content {
    padding: 25pt 30pt;
  }
  .info-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 15pt;
  }
  .info-table td {
    padding: 5pt 0;
    vertical-align: top;
  }
  .label-td {
    width: 110pt;
    color: #6b7a89;
    font-size: 9.5pt;
  }
  .value-td {
    color: #14304a;
    font-weight: bold;
    font-size: 10.5pt;
  }
  .qr-container {
    text-align: center;
    margin-top: 10pt;
  }
  .qr-img {
    width: 120pt;
    height: 120pt;
  }
  .footer-text {
    font-size: 8pt;
    color: #6b7a89;
    text-align: center;
    margin-top: 6pt;
  }
</style>
</head>
<body>
  <div class="header">
    <div class="logo">S</div>
    <div class="header-info">
      <div class="kampus-title">{{ $kampus }}</div>
      <div class="card-type">KARTU PESERTA WISUDA</div>
      <div class="periode-name">{{ $p->nama_periode }}</div>
    </div>
  </div>

  <div class="content">
    <table class="info-table">
      <tr>
        <td class="label-td">Nama</td>
        <td class="value-td">{{ $p->nama }}</td>
      </tr>
      <tr>
        <td class="label-td">NIM</td>
        <td class="value-td">{{ $p->nim }}</td>
      </tr>
      <tr>
        <td class="label-td">Fakultas</td>
        <td class="value-td">{{ $p->fakultas }}</td>
      </tr>
      <tr>
        <td class="label-td">Program Studi</td>
        <td class="value-td">{{ $p->program_studi }}</td>
      </tr>
      <tr>
        <td class="label-td">Nomor Peserta</td>
        <td class="value-td" style="color: #c8963e; font-size: 12pt;">{{ $p->nomor_peserta }}</td>
      </tr>
      <tr>
        <td class="label-td">Sesi</td>
        <td class="value-td">{{ $p->sesi ?? '-' }}</td>
      </tr>
      <tr>
        <td class="label-td">Nomor Kursi</td>
        <td class="value-td">{{ $p->nomor_kursi ?? '-' }}</td>
      </tr>
      <tr>
        <td class="label-td">Tanggal Wisuda</td>
        <td class="value-td">{{ $jadwal ? $jadwal->tanggal : $p->tanggal_wisuda }}</td>
      </tr>
      <tr>
        <td class="label-td">Waktu</td>
        <td class="value-td">{{ $jadwal ? $jadwal->waktu : '-' }}</td>
      </tr>
      <tr>
        <td class="label-td">Lokasi</td>
        <td class="value-td">{{ $jadwal ? $jadwal->lokasi : $p->lokasi_periode }}</td>
      </tr>
    </table>

    <div class="qr-container">
      <img src="{{ $qr }}" class="qr-img" alt="QR Code" />
      <div class="footer-text">Tunjukkan QR Code ini saat registrasi kehadiran</div>
    </div>
  </div>
</body>
</html>
