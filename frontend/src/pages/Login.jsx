import { useState } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import { useAuth } from '../AuthContext.jsx';
import { Field } from '../components/ui.jsx';
import logoKampus from '../../../backend-laravel/public/logo-kampus.png';

export default function Login({ adminOnly = false }) {
  const { user, login, kampus } = useAuth();
  const nav = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  if (user) return <Navigate to={user.role === 'admin' ? '/admin' : '/mahasiswa'} replace />;

  const submit = async (e) => {
    e.preventDefault();
    setError('');
    if (!email.trim() || !password) return setError(adminOnly ? 'Email dan password wajib diisi.' : 'Email/NIM dan password wajib diisi.');
    setBusy(true);
    try {
      const u = await login(email.trim(), password, adminOnly ? 'admin' : 'mahasiswa');
      nav(u.role === 'admin' ? '/admin' : '/mahasiswa', { replace: true });
    } catch (err) { setError(err.message); } finally { setBusy(false); }
  };

  return (
    <div className="login">
      <section className="login-art">
        <div className="login-brand">
          <img className="login-brand-logo" src={logoKampus} alt="Logo UIN Imam Bonjol Padang" />
          <div><span className="login-brand-name">{kampus}</span><span className="login-brand-caption">PORTAL ADMINISTRASI WISUDA</span></div>
        </div>
        <div className="login-intro">
          <span className="login-eyebrow">Satu portal, seluruh proses wisuda</span>
          <h1>Wisuda jadi<br /><span>lebih mudah.</span></h1>
          <p>Kelola pendaftaran dan administrasi wisuda secara praktis. Semua informasi penting, dari pengajuan hingga hari pelaksanaan, ada dalam satu tempat.</p>
          <ul className="login-steps">
            <li><span>01</span> Lengkapi data dan pendaftaran</li>
            <li><span>02</span> Unggah berkas persyaratan</li>
            <li><span>03</span> Pantau verifikasi secara berkala</li>
            <li><span>04</span> Akses kartu peserta dan jadwal</li>
          </ul>
        </div>
        <small className="login-copyright">© {new Date().getFullYear()} {kampus}</small>
      </section>
      <section className="login-form">
        <form className="login-box" onSubmit={submit} noValidate>
          <div className="login-form-mark"><img src={logoKampus} alt="" /><span>SIWIS <i>•</i> UIN Imam Bonjol Padang</span></div>
          <span className="login-form-eyebrow">Selamat datang kembali</span>
          <h1>{adminOnly ? 'Masuk sebagai Admin' : 'Masuk ke akun Anda'}</h1>
          <p className="login-form-description">{adminOnly ? 'Gunakan email admin dan password untuk melanjutkan.' : 'Gunakan email atau NIM dan password untuk melanjutkan.'}</p>
          {error && <div className="alert bad" role="alert">{error}</div>}
          <Field label={adminOnly ? 'Email' : 'Email atau NIM'}><input type={adminOnly ? 'email' : 'text'} value={email} onChange={(e) => setEmail(e.target.value)} placeholder={adminOnly ? 'Masukkan email admin' : 'Masukkan email atau NIM'} autoComplete="username" /></Field>
          <Field label="Password"><input type="password" value={password} onChange={(e) => setPassword(e.target.value)} placeholder="Masukkan password" autoComplete="current-password" /></Field>
          <button className="btn login-submit" disabled={busy}>{busy ? <><span className="login-spinner" />Memproses...</> : <>Masuk <span aria-hidden="true">→</span></>}</button>
          <p className="login-help">Pastikan data masuk yang digunakan sudah benar.</p>
        </form>
      </section>
    </div>
  );
}