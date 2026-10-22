import { useEffect, useState } from 'react';
import { api } from '../../api.js';
import { useToast } from '../../Toast.jsx';
import { ErrorBox, Field, Loading, PageHead, Badge, useFetch } from '../../components/ui.jsx';

export default function DataDiri() {
  const toast = useToast();
  const { data, error, loading, reload } = useFetch('/student/profil');
  const { data: programStudi, error: programStudiError, loading: programStudiLoading, reload: reloadProgramStudi } = useFetch('/program-studi');
  const [f, setF] = useState({
    nim: '',
    nama: '',
    email: '',
    no_hp: '',
    judul_skripsi: '',
    fakultas: '',
    program_studi: '',
    ipk: '',
    tahun_masuk: '',
    tahun_lulus: '',
  });
  const [busy, setBusy] = useState(false);
  useEffect(() => {
    if (data) {
      setF({
        nim: data.mahasiswa.nim || '',
        nama: data.mahasiswa.nama || '',
        email: data.mahasiswa.email || '',
        no_hp: data.mahasiswa.no_hp || '',
        judul_skripsi: data.mahasiswa.judul_skripsi || '',
        fakultas: data.mahasiswa.fakultas || '',
        program_studi: data.mahasiswa.program_studi || '',
        ipk: data.mahasiswa.ipk ?? '',
        tahun_masuk: data.mahasiswa.tahun_masuk || '',
        tahun_lulus: data.mahasiswa.tahun_lulus || '',
      });
    }
  }, [data]);
  if (loading) return <Loading />;
  if (error) return <ErrorBox message={error} onRetry={reload} />;
  const m = data.mahasiswa;
  const fakultasOptions = [...new Set((programStudi || []).map((item) => item.fakultas).filter(Boolean))].sort();
  const programStudiOptions = (programStudi || []).filter((item) => item.fakultas === f.fakultas);
  const programStudiValue = (item) => `${item.jenjang} - ${item.nama}`;
  const currentProgramStudiIsListed = programStudiOptions.some((item) => programStudiValue(item) === f.program_studi);
  const fakultasValues = new Set(fakultasOptions);
  const currentFakultasIsListed = fakultasValues.has(f.fakultas);

  const simpan = async (e) => {
    e.preventDefault();
    if (!/^[0-9]{6,20}$/.test(f.nim)) return toast.error('NIM harus berupa angka (6-20 digit).');
    if (!f.nama.trim()) return toast.error('Nama lengkap wajib diisi.');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email)) return toast.error('Format email tidak valid.');
    if (!/^[0-9+\-\s]{8,20}$/.test(f.no_hp)) return toast.error('Nomor HP tidak valid (8-20 digit).');
    if (!f.judul_skripsi.trim()) return toast.error('Judul skripsi/tugas akhir wajib diisi.');
    if (f.ipk === '' || !Number.isFinite(Number(f.ipk)) || Number(f.ipk) < 0 || Number(f.ipk) > 4) {
      return toast.error('IPK harus antara 0.00 - 4.00.');
    }
    if (f.tahun_masuk && !/^[0-9]{4}$/.test(String(f.tahun_masuk))) return toast.error('Tahun masuk harus 4 digit.');
    if (f.tahun_lulus && !/^[0-9]{4}$/.test(String(f.tahun_lulus))) return toast.error('Tahun lulus harus 4 digit.');
    if (f.tahun_masuk && f.tahun_lulus && Number(f.tahun_lulus) < Number(f.tahun_masuk)) {
      return toast.error('Tahun lulus tidak boleh sebelum tahun masuk.');
    }
    const programStudiBerubah = f.fakultas !== m.fakultas || f.program_studi !== m.program_studi;
    if (programStudiBerubah && !programStudiOptions.some((item) => programStudiValue(item) === f.program_studi)) {
      return toast.error('Pilih program studi yang sesuai dengan fakultas.');
    }
    setBusy(true);
    try { const r = await api.put('/student/profil', f); toast.success(r.message); reload(); }
    catch (err) { toast.error(err.message); } finally { setBusy(false); }
  };

  const ro = (label, value) => <Field label={label}><input value={value ?? '-'} disabled /></Field>;
  const academicFieldDisabled = data.terkunci || programStudiLoading || !!programStudiError;
  return (
    <>
      <PageHead title="Data Diri" subtitle="Perbarui data diri dan akademik selama pendaftaran masih Draft atau Revisi." />
      {data.terkunci && <div className="alert warn">Data diri terkunci karena pendaftaran Anda sedang diverifikasi atau sudah diproses.</div>}
      <form className="card" onSubmit={simpan}>
        <div className="card-head"><h2>Data akademik</h2><Badge status={m.status_kelulusan} /></div>
        <div className="form-grid">
          <Field label="NIM"><input value={f.nim} disabled={data.terkunci} onChange={(e) => setF({ ...f, nim: e.target.value })} /></Field>
          <Field label="Nama lengkap"><input value={f.nama} disabled={data.terkunci} onChange={(e) => setF({ ...f, nama: e.target.value })} /></Field>
          <Field label="Fakultas">
            <select
              value={f.fakultas}
              disabled={academicFieldDisabled}
              onChange={(e) => setF({ ...f, fakultas: e.target.value, program_studi: '' })}
            >
              <option value="">Pilih fakultas</option>
              {!currentFakultasIsListed && f.fakultas && <option value={f.fakultas}>{f.fakultas} (data saat ini)</option>}
              {fakultasOptions.map((fakultas) => <option key={fakultas} value={fakultas}>{fakultas}</option>)}
            </select>
          </Field>
          <Field label="Program studi">
            <select
              value={f.program_studi}
              disabled={academicFieldDisabled || !f.fakultas}
              onChange={(e) => setF({ ...f, program_studi: e.target.value })}
            >
              <option value="">Pilih program studi</option>
              {!currentProgramStudiIsListed && f.program_studi && <option value={f.program_studi}>{f.program_studi} (data saat ini)</option>}
              {programStudiOptions.map((item) => {
                const value = programStudiValue(item);
                return <option key={item.id} value={value}>{value}</option>;
              })}
            </select>
            {programStudiLoading && <span className="hint">Memuat daftar fakultas dan program studi...</span>}
            {programStudiError && <ErrorBox message={programStudiError} onRetry={reloadProgramStudi} />}
          </Field>
          <Field label="IPK"><input type="number" min="0" max="4" step="0.01" value={f.ipk} disabled={data.terkunci} onChange={(e) => setF({ ...f, ipk: e.target.value })} /></Field>
          <Field label="Tahun masuk"><input type="number" min="1900" max="9999" value={f.tahun_masuk} disabled={data.terkunci} onChange={(e) => setF({ ...f, tahun_masuk: e.target.value })} /></Field>
          <Field label="Tahun lulus"><input type="number" min="1900" max="9999" value={f.tahun_lulus} disabled={data.terkunci} onChange={(e) => setF({ ...f, tahun_lulus: e.target.value })} /></Field>
        </div>
        <div className="card-head mt"><h2>Data yang dapat Anda lengkapi</h2></div>
        <div className="form-grid">
          <Field label="Email"><input type="email" value={f.email} disabled={data.terkunci} onChange={(e) => setF({ ...f, email: e.target.value })} /></Field>
          <Field label="Nomor HP"><input value={f.no_hp} disabled={data.terkunci} onChange={(e) => setF({ ...f, no_hp: e.target.value })} placeholder="08xxxxxxxxxx" /></Field>
        </div>
        <Field label="Judul skripsi / tugas akhir"><textarea value={f.judul_skripsi} disabled={data.terkunci} onChange={(e) => setF({ ...f, judul_skripsi: e.target.value })} /></Field>
        {!data.terkunci && <button className="btn" disabled={busy}>{busy ? 'Menyimpan...' : 'Simpan data diri'}</button>}
      </form>
    </>
  );
}
