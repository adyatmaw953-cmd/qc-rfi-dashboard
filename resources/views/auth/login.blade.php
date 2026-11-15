<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'Laravel') }} - Masuk</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
  *{
    box-sizing: border-box;
  }

  html, body{
    margin: 0;
    padding: 0;
    min-height: 100vh;
    font-family: 'Poppins', system-ui, sans-serif;
    color: #14201A;
    background: #F4F2EB;
  }

  .login-grid{
    display: grid;
    grid-template-columns: 42% 1fr;
    min-height: 100vh;
  }

  /* ========== PANEL KIRI ========== */
  .panel-left{
    position: relative;
    background: #17291B;
    color: #FFFFFF;
    padding: 56px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    overflow: hidden;
  }

  .panel-left-svg{
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    z-index: 0;
    pointer-events: none;
  }

  .panel-left-content{
    position: relative;
    z-index: 1;
  }

  .left-header{
    display: flex;
    align-items: center;
    gap: 14px;
  }

  .left-logos{
    display: flex;
    align-items: center;
  }

  .left-logo-circle{
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #FFFFFF;
    padding: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
  }

  .left-logo-circle.second{
    margin-left: -14px;
    border: 2px solid #17291B;
  }

  .left-logo-circle img{
    width: 100%;
    height: 100%;
    object-fit: contain;
  }

  .left-brand-text{
    display: flex;
    flex-direction: column;
    line-height: 1.25;
  }

  .brand-carousel{
    height: 34px;
    overflow: hidden;
    display: block;
  }

  .brand-carousel-track{
    display: block;
    margin: 0;
    padding: 0;
    animation: brand-carousel-slide 6s ease-in-out infinite;
    will-change: transform;
  }

  .brand-carousel-slide{
    display: flex;
    flex-direction: column;
    justify-content: center;
    height: 34px;
    box-sizing: border-box;
    line-height: 1.25;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: #FFFFFF;
  }

  @keyframes brand-carousel-slide{
    0%, 44%{
      transform: translateY(0);
    }
    50%, 94%{
      transform: translateY(-34px);
    }
    100%{
      transform: translateY(-68px);
    }
  }

  .left-center{
    max-width: 380px;
  }

  .left-center h1{
    font-size: 44px;
    font-weight: 600;
    line-height: 1.15;
    letter-spacing: -0.02em;
    margin: 0 0 14px;
    color: #FFFFFF;
  }

  .left-center p{
    font-size: 16px;
    line-height: 1.65;
    color: #D5E0D2;
    margin: 0;
  }

  .left-footer{
    font-size: 13px;
    color: #B7C7B3;
  }

  /* ========== PANEL KANAN ========== */
  .panel-right{
    background: #F4F2EB;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 24px;
  }

  .panel-right-inner{
    width: 100%;
    max-width: 400px;
  }

  .panel-right h2{
    font-size: 32px;
    font-weight: 600;
    letter-spacing: -0.01em;
    margin: 0 0 6px;
    color: #14201A;
  }

  .panel-right p.lead{
    font-size: 15px;
    color: #5B665E;
    line-height: 1.5;
    margin: 0 0 24px;
  }

  .form-col{
    display: flex;
    flex-direction: column;
    gap: 18px;
    color: #14201A;
  }

  .field-wrap{
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .field-wrap label{
    font-size: 14px;
    font-weight: 600;
    color: #14201A;
  }

  .field-wrap input[type="email"],
  .field-wrap input[type="password"],
  .field-wrap input[type="text"]{
    height: 48px;
    min-height: 48px;
    width: 100%;
    box-sizing: border-box;
    padding: 0 14px;
    border: 1px solid #8C978B;
    border-radius: 10px;
    background: #FFFFFF;
    font-family: 'Poppins', system-ui, sans-serif;
    font-weight: 400;
    font-size: 15px;
    color: #14201A;
    transition: border-color .15s ease, outline-color .15s ease;
  }

  .field-wrap input::placeholder{
    color: #5B665E;
  }

  .field-wrap input:focus{
    outline: 2px solid #366F3A;
    outline-offset: 0;
    border-color: #366F3A;
  }

  .field-wrap input[aria-invalid="true"]{
    border-color: #B4470F;
  }

  .field-error-msg{
    margin: 0;
    font-size: 13px;
    font-weight: 500;
    color: #8F3F08;
  }

  .password-relative{
    position: relative;
    width: 100%;
  }

  .password-relative input{
    padding-right: 56px !important;
  }

  .password-toggle-btn{
    position: absolute;
    right: 2px;
    top: 2px;
    width: 44px;
    height: 44px;
    border: none;
    background: transparent;
    color: #5B665E;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border-radius: 8px;
  }

  .password-toggle-btn:focus-visible{
    outline: 2px solid #366F3A;
    outline-offset: 1px;
  }

  .remember-label{
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 44px;
    font-size: 14px;
    color: #5B665E;
    cursor: pointer;
    user-select: none;
  }

  .remember-label input[type="checkbox"]{
    width: 20px;
    height: 20px;
    accent-color: #366F3A;
    cursor: pointer;
    margin: 0;
  }

  .submit-btn{
    width: 100%;
    height: 52px;
    min-height: 52px;
    border-radius: 12px;
    background: #366F3A;
    color: #FFFFFF;
    font-family: 'Poppins', system-ui, sans-serif;
    font-size: 16px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: background-color .15s ease;
  }

  .submit-btn:hover{
    background: #2A5A2E;
  }

  .submit-btn:focus{
    outline: 2px solid #366F3A;
    outline-offset: 1px;
    border-color: #366F3A;
  }

  .submit-btn:disabled{
    opacity: .75;
    cursor: not-allowed;
  }

  .help-text{
    margin-top: 24px;
    text-align: center;
    font-size: 14px;
    color: #5B665E;
    line-height: 1.5;
  }

  .help-text .contact-admin{
    font-weight: 600;
    color: #366F3A;
  }

  .alert-box{
    margin-bottom: 18px;
    padding: 12px 14px;
    border-radius: 10px;
    font-size: 14px;
    line-height: 1.4;
  }

  .alert-box.success{
    background: #E8F2E6;
    color: #26501F;
    border: 1px solid #C4DEC0;
  }

  .alert-box.error{
    background: #FCE8D8;
    color: #8F3F08;
    border: 1px solid #F0C7A8;
    font-size: 14px;
    font-weight: 500;
  }

  /* ========== RESPONSIF (<= 900px) ========== */
  @media (max-width: 900px){
    .login-grid{
      grid-template-columns: 1fr;
      min-height: 100vh;
      background: #17291B;
    }

    .panel-left{
      height: 200px;
      min-height: 200px;
      max-height: 200px;
      padding: 24px 20px 32px;
      justify-content: flex-start;
      gap: 12px;
    }

    .left-header{
      gap: 12px;
    }

    .left-logo-circle{
      width: 40px;
      height: 40px;
    }

    .left-logo-circle.second{
      margin-left: -10px;
    }

    .brand-carousel,
    .brand-carousel-slide{
      height: 28px;
      font-size: 11px;
    }

    @keyframes brand-carousel-slide{
      0%, 44%{ transform: translateY(0); }
      50%, 94%{ transform: translateY(-28px); }
      100%{ transform: translateY(-56px); }
    }

    .panel-left-content.left-center{
      display: block;
      margin: 0;
    }

    .left-center h1{
      font-size: 22px;
      margin: 0;
      color: #FFFFFF;
      font-weight: 600;
    }

    .left-center p{
      display: none;
    }

    .panel-left-content.left-footer{
      display: none;
    }

    .panel-right{
      background: #F4F2EB;
      border-top-left-radius: 24px;
      border-top-right-radius: 24px;
      margin-top: -24px;
      position: relative;
      z-index: 2;
      padding: 36px 20px 48px;
      align-items: flex-start;
      min-height: calc(100vh - 176px);
    }
  }

  @media (prefers-reduced-motion: reduce){
    .submit-btn,
    .field-wrap input{
      transition: none;
    }
  }
</style>
</head>
<body>

<div class="login-grid">

  <!-- ============ PANEL KIRI ============ -->
  <div class="panel-left">
    <svg class="panel-left-svg" viewBox="0 0 600 900" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
      <path d="M-20,120 C120,60 220,180 340,110 S560,40 640,130" stroke="#FFFFFF" stroke-opacity="0.10" stroke-width="1.5" fill="none"/>
      <path d="M-20,260 C140,190 260,320 400,240 S600,170 660,270" stroke="#FFFFFF" stroke-opacity="0.10" stroke-width="1.5" fill="none"/>
      <path d="M-20,420 C160,520 280,360 420,460 S600,560 660,440" stroke="#5C9760" stroke-opacity="0.30" stroke-width="1.5" fill="none"/>
      <path d="M-20,560 C150,470 300,620 440,520 S610,430 660,540" stroke="#5C9760" stroke-opacity="0.30" stroke-width="1.5" fill="none"/>
      <path d="M-20,720 C170,660 300,780 440,700 S610,640 660,730" stroke="#FFFFFF" stroke-opacity="0.10" stroke-width="1.5" fill="none"/>
    </svg>

    <div class="panel-left-content left-header">
      <div class="left-logos">
        <div class="left-logo-circle">
          <img src="{{ asset('images/mei.png') }}" alt="Logo PT Mudalaya Energy Indonesia">
        </div>
        <div class="left-logo-circle second">
          <img src="{{ asset('images/mgi.png') }}" alt="Logo PT Mutiara Global Industry">
        </div>
      </div>
      <div class="left-brand-text">
        <span class="brand-carousel" aria-label="PT. Mudalaya Energy Indonesia / PT. Mutiara Global Industry">
          <span class="brand-carousel-track" aria-hidden="true">
            <span class="brand-carousel-slide">PT. MUDALAYA ENERGY<br>INDONESIA</span>
            <span class="brand-carousel-slide">PT. MUTIARA GLOBAL<br>INDUSTRY</span>
            <span class="brand-carousel-slide">PT. MUDALAYA ENERGY<br>INDONESIA</span>
          </span>
        </span>
      </div>
    </div>

    <div class="panel-left-content left-center">
      <h1>Senang bertemu lagi.</h1>
      <p>Masuk untuk melanjutkan pekerjaanmu tepat dari titik terakhir kamu tinggalkan.</p>
    </div>

    <div class="panel-left-content left-footer">
      Sistem RFI, QC Finding, NCR, dan MDR
    </div>
  </div>

  <!-- ============ PANEL KANAN ============ -->
  <div class="panel-right">
    <div class="panel-right-inner">
      <h2>Masuk</h2>
      <p class="lead">Masuk dengan email dan kata sandi akun kamu.</p>

      @if (session('status'))
        <div class="alert-box success" role="status">{{ session('status') }}</div>
      @endif

      @if (session('error'))
        <div class="alert-box error" role="alert">{{ session('error') }}</div>
      @endif

      @if ($errors->has('email'))
        <div class="alert-box error" role="alert">
          Email atau kata sandi tidak cocok. Coba periksa lagi ya.
        </div>
      @endif

      <form action="{{ route('login.attempt') }}" method="POST" id="loginForm" class="form-col">
        @csrf

        <div class="field-wrap">
          <label for="email">Email</label>
          <input
            type="email"
            id="email"
            name="email"
            placeholder="nama@perusahaan.com"
            value="{{ old('email') }}"
            autocomplete="username"
            required
            @if ($errors->has('email'))
              aria-invalid="true"
              aria-describedby="email-error"
            @endif
          >
          @if ($errors->has('email'))
            <p class="field-error-msg" id="email-error">Email atau kata sandi tidak cocok.</p>
          @endif
        </div>

        <div class="field-wrap">
          <label for="password">Kata sandi</label>
          <div class="password-relative">
            <input
              type="password"
              id="password"
              name="password"
              placeholder="••••••••"
              autocomplete="current-password"
              required
            >
            <button
              type="button"
              id="togglePassword"
              class="password-toggle-btn"
              aria-label="Tampilkan kata sandi"
            >
              <svg id="eyeIcon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"></path>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
            </button>
          </div>
        </div>

        <div>
          <label class="remember-label">
            <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
            Ingat saya
          </label>
        </div>

        <button type="submit" class="submit-btn" id="submitBtn">Masuk</button>
      </form>

      <div class="help-text">
        Lupa kata sandi atau belum punya akun? <span class="contact-admin">Hubungi admin.</span>
      </div>
    </div>
  </div>

</div>

<script>
  const toggleBtn = document.getElementById('togglePassword');
  const passwordInput = document.getElementById('password');
  const eyeIcon = document.getElementById('eyeIcon');

  const eyeOpen = '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"></path><circle cx="12" cy="12" r="3"></circle>';
  const eyeClosed = '<path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a20.6 20.6 0 0 1 5.06-5.94M9.9 4.24A10.6 10.6 0 0 1 12 4c7 0 11 7 11 7a20.5 20.5 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';

  if (toggleBtn && passwordInput && eyeIcon) {
    toggleBtn.addEventListener('click', () => {
      const isPassword = passwordInput.type === 'password';
      passwordInput.type = isPassword ? 'text' : 'password';
      eyeIcon.innerHTML = isPassword ? eyeClosed : eyeOpen;
      toggleBtn.setAttribute('aria-label', isPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
    });
  }

  const loginForm = document.getElementById('loginForm');
  const submitBtn = document.getElementById('submitBtn');

  if (loginForm && submitBtn) {
    loginForm.addEventListener('submit', () => {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Memproses...';
    });
  }
</script>

</body>
</html>