<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= html_escape($title ?? 'SATUKES') ?> · SATUKES</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/css/tabler.min.css">
  <style>
    :root{--satukes:#0f766e}.navbar-brand-mark{width:34px;height:34px;border-radius:10px;background:var(--satukes);display:grid;place-items:center;color:#fff;font-weight:800}.metric-icon{width:44px;height:44px;border-radius:12px;display:grid;place-items:center}.chart{height:220px;display:flex;gap:.5rem;align-items:flex-end;padding-top:1rem}.chart-item{flex:1;min-width:12px;text-align:center}.chart-bar{background:linear-gradient(180deg,#14b8a6,#0f766e);border-radius:6px 6px 2px 2px;min-height:3px}.chart-label{font-size:10px;color:#667085;margin-top:6px;white-space:nowrap}.status-dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:6px}.page-wrapper{min-height:100vh}@media(max-width:767px){.chart-label{display:none}.page-header .btn-list{width:100%}.page-header .btn-list .btn{flex:1}}
  </style>
</head>
<body>
<div class="page">
  <header class="navbar navbar-expand-md d-print-none">
    <div class="container-xl">
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu"><span class="navbar-toggler-icon"></span></button>
      <a class="navbar-brand navbar-brand-autodark d-flex gap-2 align-items-center" href="<?= site_url('dashboard') ?>"><span class="navbar-brand-mark">S</span><span>SATUKES</span></a>
      <div class="navbar-nav flex-row order-md-last"><div class="nav-item dropdown"><a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown"><span class="avatar avatar-sm bg-teal-lt"><?= html_escape(strtoupper(substr($currentUser->name,0,2))) ?></span><div class="d-none d-xl-block ps-2"><div><?= html_escape($currentUser->name) ?></div><div class="mt-1 small text-secondary"><?= html_escape($currentUser->email) ?></div></div></a><div class="dropdown-menu dropdown-menu-end"><?= form_open('logout',array('class'=>'m-0')) ?><button class="dropdown-item" type="submit">Keluar</button><?= form_close() ?></div></div></div>
      <div class="collapse navbar-collapse" id="navbar-menu"><div class="d-flex flex-column flex-md-row flex-fill align-items-stretch align-items-md-center"><ul class="navbar-nav">
        <?php if($can('dashboard.view')): ?><li class="nav-item"><a class="nav-link" href="<?= site_url('dashboard') ?>"><span class="nav-link-title">Dashboard</span></a></li><?php endif ?>
        <?php if($can('branches.view')): ?><li class="nav-item"><a class="nav-link" href="<?= site_url('faskes') ?>"><span class="nav-link-title">Faskes</span></a></li><?php endif ?>
        <?php if($can('users.manage')): ?><li class="nav-item"><a class="nav-link" href="<?= site_url('users') ?>"><span class="nav-link-title">Pengguna & Akses</span></a></li><?php endif ?>
      </ul></div></div>
    </div>
  </header>
  <div class="page-wrapper">
    <div class="page-body"><div class="container-xl">
      <?php if($this->session->flashdata('success')): ?><div class="alert alert-success" role="alert"><?= html_escape($this->session->flashdata('success')) ?></div><?php endif ?>
      <?php if($this->session->flashdata('error')): ?><div class="alert alert-danger" role="alert"><?= html_escape($this->session->flashdata('error')) ?></div><?php endif ?>
      <?php $this->load->view($contentView); ?>
    </div></div>
    <footer class="footer footer-transparent d-print-none"><div class="container-xl"><div class="text-center text-secondary">SATUKES · Dashboard Monitoring Fasilitas Kesehatan</div></div></footer>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/js/tabler.min.js"></script>
</body></html>
