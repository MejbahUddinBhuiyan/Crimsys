<?php
// Landing page
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>CRIMSYS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="/Crimsys/css/styles.css" rel="stylesheet">
  <style>
    .topbar{
      background:#0b0b0d;
      border-bottom:1px solid rgba(225,29,72,.2);
      position:sticky; top:0; z-index:10;
    }
    .topbar .inner{
      max-width:1100px;
      margin:0 auto;
      padding:10px 16px;
      display:flex;
      align-items:center;
      justify-content:space-between;
    }

    .hero{
      min-height:calc(100vh - 60px);
      display:flex;
      align-items:center;
      justify-content:center;
      padding:24px;
    }
    .glass-card{
      width:min(920px, 92%);
      padding:28px 28px 32px;
      border-radius:16px;
      background:rgba(255,255,255,.06);
      border:1px solid rgba(255,255,255,.18);
      backdrop-filter:blur(12px) saturate(140%);
      -webkit-backdrop-filter:blur(12px) saturate(140%);
      box-shadow:0 18px 40px rgba(0,0,0,.35);
    }
    .title{
      text-align:center;
      color:#fff;
      font-size:2rem;
      font-weight:700;
      margin:0 0 8px;
    }
    .subtitle{
      text-align:center;
      color:#d0d0d0;
      margin-bottom:26px;
    }
    .accent{ color:var(--accent); }

    .options{
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:24px;
    }
    .opt{
      border:1px dashed rgba(255,255,255,.18);
      border-radius:12px;
      padding:18px;
      min-height: 170px;
      display:flex;
      flex-direction:column;
      justify-content:center; /* centers content vertically */
    }
    .opt h5{ color:#fff; margin:0 0 6px; text-align:center; }
    .opt p{ color:#cfcfcf; font-size:.95rem; text-align:center; margin-bottom: 12px; }

    /* Center the button horizontally */
    .btn-row{
      margin-top:6px;
      display:flex;
      justify-content:center;   /* <-- centers horizontally */
    }
    .btn-small{
      min-width:220px;
      max-width:260px;
      width:100%;
      padding:.65rem 1.1rem;
      border-radius:10px;
      text-align:center;
      font-weight:600;
      transition: transform .07s ease, background .2s ease, border-color .2s ease;
    }
    .btn-small:hover{ transform:translateY(-1px); }

    @media (max-width: 750px){
      .options{ grid-template-columns:1fr; }
      .title{ font-size:1.6rem; }
    }
  </style>
</head>
<body class="radish-black-bg">

  <!-- Topbar -->
  <nav class="topbar">
    <div class="inner">
      <a href="/Crimsys/" class="brand-logo">CRIMSYS</a>
      <a href="/Crimsys/html/admin_login.html" class="btn btn-outline-accent btn-sm">Admin</a>
    </div>
  </nav>

  <!-- Center content -->
  <main class="hero">
    <div class="glass-card">
      <h1 class="title">Welcome to <span class="accent">CRIMSYS</span></h1>
      <p class="subtitle">
        A secure platform for policing operations, crime records, citizen reports and more — built with performance, safety, and clarity.
      </p>

      <div class="options">
        <div class="opt">
          <h5>Are you a Cop?</h5>
          <p>Log in to your duty panel using your Cop ID and password.</p>
          <div class="btn-row">
            <a href="/Crimsys/html/login.html" class="btn btn-outline-accent btn-small">Cop Login</a>
          </div>
        </div>

        <div class="opt">
          <h5>Are you a Citizen?</h5>
          <p>Proceed to the citizen portal (coming soon).</p>
          <div class="btn-row">
            <a href="#" class="btn btn-outline-accent btn-small">Citizen Portal</a>
          </div>
        </div>
      </div>
    </div>
  </main>

</body>
</html>
