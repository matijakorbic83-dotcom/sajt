<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/admin_auth.php';
admin_require_login();

$currentUser = admin_current_user();
$csrfToken = admin_csrf_token();
?>
<!doctype html>
<html lang="sr" data-i18n-storage-key="smilecraft-admin-locale" data-theme-storage-key="smilecraft-admin-theme">
<head>
  <script src="../js/theme.js?v=20261114-theme"></script>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f3f6f7">
  <title data-i18n="admin.panelTitle">SmileCraft Dental — Admin</title>
  <link rel="stylesheet" href="../css/language-picker.css">
  <script src="../js/i18n.js" defer></script>
  <style>
    :root {
      color-scheme: light;
      --ink: #172b3a;
      --muted: #647789;
      --line: #e3ebf1;
      --surface: #fff;
      --canvas: #f5f9fc;
      --brand: #2d8fcb;
      --brand-dark: #2378ad;
      --brand-soft: #eaf6fd;
      --green: #18734f;
      --green-soft: #eaf6ef;
      --amber: #99620b;
      --amber-soft: #fff5df;
      --red: #a23b44;
      --red-soft: #fff0f1;
      --shadow: 0 10px 28px #19313b0a;
    }
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; background: var(--canvas); color: var(--ink); font: 15px/1.5 Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }
    button, input, select, textarea { font: inherit; }
    button, a { -webkit-tap-highlight-color: transparent; }
    button { cursor: pointer; }
    .app-header { height: 76px; padding: 0 2.2rem; background: var(--surface); border-bottom: 1px solid var(--line); display:flex; align-items:center; justify-content:space-between; gap:1.5rem; position:sticky; top:0; z-index:5; }
    .brand { display:flex; align-items:center; gap:.8rem; min-width:220px; }
    .brand-mark { width:42px;height:42px;border-radius:13px;background:var(--brand-soft);display:grid;place-items:center;color:var(--brand);font-size:23px;font-weight:800; }
    .brand-name { font-weight:760; letter-spacing:-.02em; }
    .brand-caption { display:block;color:var(--muted);font-size:.77rem; }
    .header-right { display:flex;align-items:center;gap:1.1rem; }
    .user-info { color:var(--muted);font-size:.9rem; }
    .user-info strong { color:var(--ink); }
    .language-control { display:flex;align-items:center;gap:.45rem;color:var(--muted);font-size:.82rem; }
    .language-control select { min-height:2.4rem;width:auto;padding:.45rem .65rem;background:white; }
    .layout { display:grid;grid-template-columns:246px minmax(0,1fr);min-height:calc(100vh - 76px); }
    .sidebar { padding:1.6rem 1rem;border-right:1px solid var(--line);background:#f8fafb; }
    .side-label { margin:.3rem .8rem 1rem;color:#91a0a5;font-size:.68rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase; }
    .nav-list { display:grid;gap:.35rem; }
    .nav-item { width:100%;display:flex;align-items:center;gap:.8rem;min-height:46px;padding:.65rem .8rem;border:0;border-radius:10px;background:transparent;color:#53676e;text-align:left;font-weight:650; }
    .nav-item:hover { background:#edf2f3; }
    .nav-item[aria-current="page"] { background:var(--brand-soft);color:var(--brand-dark); }
    .nav-icon { width:23px;text-align:center;font-size:1.05rem; }
    .sidebar-note { margin:2rem .65rem 0;padding:1rem;border:1px solid var(--line);border-radius:12px;background:white;color:var(--muted);font-size:.78rem; }
    .main { width:min(1500px,100%);padding:2rem clamp(1.2rem,3vw,3rem) 4rem; }
    .page-heading { display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:1.5rem; }
    .eyebrow { margin:0 0 .4rem;color:var(--brand);font-size:.72rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase; }
    h1 { margin:0;font-size:clamp(1.8rem,3vw,2.4rem);line-height:1.15;letter-spacing:-.045em; }
    h2 { margin:0;font-size:1.15rem;letter-spacing:-.02em; }
    .subheading { margin:.5rem 0 0;color:var(--muted); }
    .auto-refresh-status { margin:.55rem 0 1rem;color:var(--muted);font-size:.8rem; }
    .section { display:none; }
    .section.active { display:block; }
    .card { border:1px solid var(--line);border-radius:15px;background:var(--surface);box-shadow:var(--shadow); }
    .card-heading { padding:1.2rem 1.35rem;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;gap:1rem; }
    .card-body { padding:1.25rem 1.35rem; }
    .metric-grid { display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem;margin-bottom:1rem; }
    .metric { min-height:135px;padding:1.2rem 1.35rem; }
    .metric-label { color:var(--muted);font-size:.78rem;font-weight:750;letter-spacing:.07em;text-transform:uppercase; }
    .metric-value { margin-top:.65rem;font-size:2.4rem;font-weight:780;line-height:1;letter-spacing:-.05em; }
    .metric-note { margin-top:.6rem;color:var(--muted);font-size:.8rem; }
    .toolbar { display:flex;align-items:flex-end;flex-wrap:wrap;gap:.8rem;padding:1rem 1.15rem;margin-bottom:1rem; }
    .field { display:grid;gap:.35rem;min-width:160px;flex:1; }
    .field.grow { flex:2;min-width:220px; }
    label { color:#64767c;font-size:.74rem;font-weight:750;letter-spacing:.06em;text-transform:uppercase; }
    input, select, textarea { width:100%;min-height:42px;padding:.62rem .75rem;border:1px solid #d5e0e3;border-radius:8px;background:#fff;color:var(--ink); }
    textarea { min-height:108px;resize:vertical; }
    input:focus-visible,select:focus-visible,textarea:focus-visible,button:focus-visible,a:focus-visible { outline:3px solid #146b7730;outline-offset:2px; }
    .button { display:inline-flex;align-items:center;justify-content:center;gap:.4rem;min-height:41px;padding:.55rem .9rem;border:1px solid #d5e0e3;border-radius:9px;background:#fff;color:var(--ink);font-weight:680;white-space:nowrap; }
    .button:hover { background:#f7fafb; }
    .button.primary { background:var(--brand);border-color:var(--brand);color:#081e2d; }
    .button.primary:hover { background:var(--brand-dark); }
    .button.subtle { background:var(--brand-soft);border-color:#d6e9e9;color:var(--brand-dark); }
    .button.danger { background:var(--red-soft);border-color:#f1d0d2;color:var(--red); }
    .button.small { min-height:34px;padding:.35rem .65rem;font-size:.83rem; }
    .button:disabled { opacity:.55;cursor:wait; }
    .table-wrap { overflow:auto; }
    table { width:100%;min-width:800px;border-collapse:collapse; }
    th,td { padding:.9rem 1rem;border-bottom:1px solid #edf1f2;text-align:left;vertical-align:middle; }
    th { background:#fafcfc;color:#728187;font-size:.7rem;font-weight:800;letter-spacing:.09em;text-transform:uppercase;white-space:nowrap; }
    td { font-size:.9rem; }
    tbody tr:hover { background:#fbfdfd; }
    tbody tr:last-child td { border-bottom:0; }
    .patient-primary { font-weight:700; }
    .secondary { display:block;color:var(--muted);font-size:.8rem; }
    .pill { display:inline-flex;padding:.28rem .62rem;border-radius:999px;font-size:.75rem;font-weight:760; }
    .pill.pending { color:var(--amber);background:var(--amber-soft); }
    .pill.confirmed,.pill.published { color:var(--green);background:var(--green-soft); }
    .pill.cancelled,.pill.unpublished,.pill.inactive { color:var(--red);background:var(--red-soft); }
    .pill.active { color:var(--green);background:var(--green-soft); }
    .row-actions { display:flex;gap:.4rem;align-items:center; }
    .empty-state,.loading-state { padding:2.2rem 1rem;text-align:center;color:var(--muted); }
    .notice { margin:0 0 1rem;padding:.85rem 1rem;border:1px solid #cfe2e2;border-radius:10px;background:#f2f9f9;color:#45646b;font-size:.87rem; }
    .flash { margin:0 0 1rem;padding:.85rem 1rem;border-radius:9px;background:#edf6f1;color:var(--green); }
    .flash.error { background:var(--red-soft);color:var(--red); }
    .admin-shell-fallback { margin:0 0 1rem;padding:1rem 1.1rem;border:1px solid #f1c7c7;border-radius:12px;background:#fff5f5;color:#8f3a3a;line-height:1.5; }
    .admin-shell-fallback strong { display:block;margin-bottom:.35rem; }
    .service-groups { display:grid;gap:1.35rem; }
    .service-category h2 { margin:0 0 .75rem;font-size:1.05rem; }
    .service-category-grid,.content-grid { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.9rem; }
    .item-card { padding:1.1rem 1.15rem; }
    .item-top { display:flex;align-items:flex-start;justify-content:space-between;gap:.8rem; }
    .item-card h3 { margin:0;font-size:1rem; }
    .item-card p { margin:.6rem 0;color:var(--muted);font-size:.88rem;white-space:pre-wrap;overflow-wrap:anywhere; }
    .item-meta { display:flex;flex-wrap:wrap;gap:.45rem;margin-top:.85rem; }
    .card-actions { display:flex;gap:.45rem;flex-wrap:wrap;margin-top:1rem; }
    .mini-schedule { display:grid;gap:.5rem; }
    .mini-row { display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.7rem .8rem;border:1px solid #edf1f2;border-radius:9px; }
    .mini-time { min-width:62px;color:var(--brand);font-weight:760;font-variant-numeric:tabular-nums; }
    .mini-patient { flex:1;font-weight:650; }
    .hours-grid { display:grid;grid-template-columns:1fr;gap:.55rem; }
    .hours-toolbar { display:flex;flex-wrap:wrap;gap:.45rem;margin-bottom:.75rem; }
    .hour-row { padding:.85rem 1rem;border:1px solid var(--line);border-radius:10px;display:grid;grid-template-columns:minmax(130px,1.25fr) 110px repeat(4,minmax(105px,1fr));align-items:end;gap:.65rem; }
    .hour-day { align-self:center;font-weight:700; }
    .hour-row label { font-size:.64rem;letter-spacing:.03em; }
    .hour-row input { min-width:0;padding:.5rem .55rem;font-variant-numeric:tabular-nums; }
    .closed-toggle { display:flex;align-items:center;gap:.45rem;align-self:center;font-size:.83rem;color:var(--muted); }
    .closed-toggle input { width:17px;height:17px;min-height:auto; }
    .profile-form { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem; }
    .profile-form .full { grid-column:1/-1; }
    .form-actions { display:flex;justify-content:flex-end;gap:.6rem;margin-top:1.2rem; }
    .dialog { width:min(680px,calc(100% - 1.4rem));max-height:calc(100vh - 2rem);padding:0;border:1px solid var(--line);border-radius:16px;background:white;color:var(--ink);box-shadow:0 28px 90px #142b3552; }
    .dialog::backdrop { background:#152b33a8;backdrop-filter:blur(3px); }
    .dialog-header { display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1.15rem 1.35rem;border-bottom:1px solid var(--line); }
    .dialog-content { padding:1.2rem 1.35rem; }
    .dialog-close { width:35px;height:35px;border:0;border-radius:8px;background:#f1f5f6;color:var(--muted);font-size:1.3rem; }
    .dialog-grid { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.9rem; }
    .dialog-grid .full { grid-column:1/-1; }
    .sr-only { position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0; }
    @media(max-width:1100px) {
      .layout { grid-template-columns:205px minmax(0,1fr); }
      .metric-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
      .hour-row { grid-template-columns:repeat(4,minmax(0,1fr)); }
      .hour-day { grid-column:1/3; }
      .closed-toggle { grid-column:3/5;justify-self:end; }
      .hour-row .field { grid-column:auto; }
    }
    @media(max-width:760px) {
      .app-header { height:auto;min-height:68px;padding:.75rem 1rem; }
      .brand { min-width:auto; }
      .brand-caption,.user-info,.language-control > span { display:none; }
      .header-right { gap:.6rem; }
      .layout { display:block; }
      .sidebar { position:sticky;top:68px;z-index:4;padding:.45rem .65rem;border-right:0;border-bottom:1px solid var(--line);overflow-x:auto; }
      .side-label,.sidebar-note { display:none; }
      .nav-list { display:flex;width:max-content;gap:.3rem; }
      .nav-item { width:auto;min-height:42px;padding:.5rem .7rem; }
      .main { padding:1.25rem .8rem 3rem; }
      .page-heading { align-items:flex-start; }
      .service-category-grid,.content-grid { grid-template-columns:1fr; }
    }
    @media(max-width:540px) {
      .brand-mark { width:36px;height:36px; }
      .brand-name { font-size:.92rem; }
      .language-control select { min-height:2.2rem;padding:.35rem .35rem;max-width:95px; }
      .header-right form .button { min-height:36px;padding:.4rem .55rem;font-size:.8rem; }
      .metric-grid { gap:.55rem; }
      .metric { min-height:112px;padding:.9rem; }
      .metric-value { font-size:1.9rem; }
      .metric-label { font-size:.66rem; }
      .profile-form,.dialog-grid { grid-template-columns:1fr; }
      .profile-form .full,.dialog-grid .full { grid-column:auto; }
      .hour-row { grid-template-columns:1fr 1fr; }
      .hour-day { grid-column:1/2; }
      .closed-toggle { grid-column:2/3;justify-self:end; }
      .hour-row .field { min-width:0; }
      .card-heading,.card-body { padding:1rem; }
    }
  </style>
  <link rel="stylesheet" href="../css/theme.css?v=20261114-theme">
</head>
<body>
  <header class="app-header">
    <a class="brand" href="index.php" aria-label="SmileCraft Dental admin">
      <span class="brand-mark" aria-hidden="true">✦</span>
      <span><span class="brand-name">SmileCraft Dental</span><span class="brand-caption" data-i18n="admin.panelCaption">Clinic administration</span></span>
    </a>
    <div class="header-right">
      <button class="theme-toggle" type="button" data-theme-toggle></button>
      <div class="language-control">
        <span data-i18n="language.label">Language</span>
        <select data-language-select aria-label="Select language" data-i18n-attr="aria-label:language.select">
          <option value="sr-Latn" lang="sr-Latn">Srpski — Latinica</option>
          <option value="sr-Cyrl" lang="sr-Cyrl">Српски — Ћирилица</option>
          <option value="en" lang="en">English</option>
          <option value="ru" lang="ru">Русский</option>
          <option value="de" lang="de">Deutsch</option>
        </select>
      </div>
      <span class="user-info"><span data-i18n="admin.loggedInAs">Signed in as</span> <strong><?= htmlspecialchars($currentUser['username'] ?? 'admin', ENT_QUOTES, 'UTF-8') ?></strong></span>
      <form method="post" action="logout.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <button class="button" type="submit" data-i18n="admin.logout">Sign out</button>
      </form>
    </div>
  </header>
  <div class="layout">
    <aside class="sidebar">
      <p class="side-label" data-i18n="admin.workspace">Workspace</p>
      <nav class="nav-list" aria-label="Administrator sections">
        <button class="nav-item" type="button" data-section-link="overview" aria-current="page"><span class="nav-icon">⌂</span><span data-i18n="admin.overview">Overview</span></button>
        <button class="nav-item" type="button" data-section-link="appointments"><span class="nav-icon">▦</span><span data-i18n="admin.appointments">Appointments</span></button>
        <button class="nav-item" type="button" data-section-link="services"><span class="nav-icon">✚</span><span data-i18n="admin.services">Services</span></button>
        <button class="nav-item" type="button" data-section-link="content"><span class="nav-icon">▤</span><span data-i18n="admin.content">Website content</span></button>
        <button class="nav-item" type="button" data-section-link="clinic"><span class="nav-icon">⚙</span><span data-i18n="admin.clinicProfile">Clinic profile</span></button>
      </nav>
      <p class="sidebar-note" data-i18n="admin.scopeNote">Records and changes are restricted to your clinic.</p>
    </aside>
    <main class="main">
      <p id="flash" class="flash" role="status" aria-live="polite" hidden></p>
      <p id="auto-refresh-status" class="auto-refresh-status" role="status" aria-live="off" hidden></p>
      <div id="admin-shell-fallback" class="admin-shell-fallback" hidden></div>
      <section class="section active" id="section-overview">
        <div class="page-heading"><div><p class="eyebrow" data-i18n="admin.dashboardEyebrow">Clinic workspace</p><h1 data-i18n="admin.overview">Overview</h1><p id="welcome-copy" class="subheading"></p></div><button class="button primary" type="button" data-go="appointments" data-i18n="admin.manageAppointments">Manage appointments</button></div>
        <div class="metric-grid" id="metrics" aria-live="polite"></div>
        <div class="card">
          <div class="card-heading"><div><h2 data-i18n="admin.todaySchedule">Today at a glance</h2><p class="subheading" data-i18n="admin.todayScheduleNote">Active appointment requests for this clinic.</p></div><button class="button subtle" type="button" data-go="appointments" data-i18n="admin.viewAppointments">View appointments</button></div>
          <div class="card-body"><p class="notice" data-i18n="admin.patientMetricDefinition">Patients are estimated as distinct appointment email addresses, falling back to phone number; no separate patient registry exists.</p><div id="overview-message" class="mini-schedule" aria-live="polite"></div></div>
        </div>
      </section>

      <section class="section" id="section-appointments">
        <div class="page-heading"><div><p class="eyebrow" data-i18n="admin.bookingEyebrow">Scheduling</p><h1 data-i18n="admin.appointments">Appointments</h1><p class="subheading" data-i18n="admin.appointmentsIntro">Search, review, reschedule, confirm, or cancel appointment requests.</p></div></div>
        <form class="toolbar card" id="appointment-filters">
          <div class="field grow"><label for="appointment-search" data-i18n="admin.search">Search</label><input id="appointment-search" name="search" type="search" maxlength="120" placeholder="Patient, email, phone, service or ID" data-i18n-attr="placeholder:admin.searchPlaceholder"></div>
          <div class="field"><label for="appointment-date" data-i18n="admin.date">Date</label><input id="appointment-date" name="date" type="date"></div>
          <div class="field"><label for="appointment-status" data-i18n="admin.status">Status</label><select id="appointment-status" name="status"><option value="active" data-i18n="admin.activeAppointments">Active appointments</option><option value="all" data-i18n="admin.allStatuses">All statuses</option><option value="pending" data-i18n="admin.pending">Pending</option><option value="confirmed" data-i18n="admin.confirmed">Confirmed</option><option value="cancelled" data-i18n="admin.cancelled">Cancelled</option></select></div>
          <button class="button primary" type="submit" data-i18n="admin.applyFilters">Apply</button><button class="button" type="reset" id="reset-appointment-filters" data-i18n="admin.reset">Reset</button>
        </form>
        <div class="card table-wrap"><table><thead><tr><th data-i18n="admin.patient">Patient</th><th data-i18n="admin.service">Service</th><th data-i18n="admin.appointmentDateTime">Date and time</th><th data-i18n="admin.status">Status</th><th data-i18n="admin.actions">Actions</th></tr></thead><tbody id="appointment-rows"><tr><td class="loading-state" colspan="5" data-i18n="admin.loading">Loading…</td></tr></tbody></table></div>
      </section>

      <section class="section" id="section-services">
        <div class="page-heading"><div><p class="eyebrow" data-i18n="admin.catalogEyebrow">Booking catalog</p><h1 data-i18n="admin.services">Services</h1><p class="subheading" data-i18n="admin.servicesIntro">Manage the services available in the public booking form.</p></div><button class="button primary" type="button" id="add-service" data-i18n="admin.addService">Add service</button></div>
        <div id="service-list" class="service-groups"></div>
      </section>

      <section class="section" id="section-content">
        <div class="page-heading"><div><p class="eyebrow" data-i18n="admin.contentEyebrow">Content library</p><h1 data-i18n="admin.content">Website content</h1><p class="subheading" data-i18n="admin.contentIntro">Manage clinic profiles, reviews, gallery entries, and FAQs in your clinic library.</p></div><button class="button primary" type="button" id="add-content" data-i18n="admin.addContent">Add content</button></div>
        <p class="notice" data-i18n="admin.contentScopeNote">These records are stored and managed here. Public pages currently use static content and do not yet render these records.</p>
        <form class="toolbar card" id="content-filters"><div class="field"><label for="content-type-filter" data-i18n="admin.contentType">Content type</label><select id="content-type-filter"><option value="all" data-i18n="admin.allContentTypes">All content</option><option value="dentist" data-i18n="admin.dentist">Dentist profile</option><option value="review" data-i18n="admin.review">Review</option><option value="gallery" data-i18n="admin.gallery">Gallery</option><option value="faq" data-i18n="admin.faq">FAQ</option></select></div></form>
        <div id="content-list" class="content-grid"></div>
      </section>

      <section class="section" id="section-clinic">
        <div class="page-heading"><div><p class="eyebrow" data-i18n="admin.settingsEyebrow">Clinic settings</p><h1 data-i18n="admin.clinicProfile">Clinic profile</h1><p class="subheading" data-i18n="admin.clinicIntro">Keep contact details and public opening hours up to date.</p></div></div>
        <form id="clinic-form" class="card"><div class="card-heading"><h2 data-i18n="admin.contactDetails">Contact details</h2></div><div class="card-body">
          <div class="profile-form">
            <div class="field"><label for="clinic-name" data-i18n="admin.clinicName">Clinic name</label><input id="clinic-name" name="name" maxlength="190" required></div>
            <div class="field"><label for="clinic-email" data-i18n="admin.email">Email</label><input id="clinic-email" name="email" type="email" maxlength="190"></div>
            <div class="field"><label for="clinic-phone" data-i18n="admin.phone">Phone</label><input id="clinic-phone" name="phone" maxlength="40"></div>
            <div class="field"><label for="clinic-address" data-i18n="admin.address">Address</label><input id="clinic-address" name="address" maxlength="255"></div>
          </div>
        </div><div class="card-heading"><div><h2 data-i18n="admin.openingHours">Opening hours</h2><p class="subheading" data-i18n="admin.openingHoursNote">These hours also control the available online booking slots. Slot starts remain on a 30-minute grid.</p></div></div>
          <div class="card-body">
            <div class="hours-toolbar">
              <button class="button small" type="button" id="copy-weekday-hours" data-i18n="admin.copyWeekdayHours">Primeni na radne dane</button>
              <button class="button small" type="button" id="copy-all-hours" data-i18n="admin.copyAllHours">Primeni na celu nedelju</button>
            </div>
            <div id="opening-hours" class="hours-grid"></div>
            <div class="form-actions"><button class="button primary" type="submit" data-i18n="admin.saveClinic">Save clinic settings</button></div>
          </div></form>
      </section>
    </main>
  </div>

  <dialog class="dialog" id="appointment-dialog"><div class="dialog-header"><h2 data-i18n="admin.appointmentDetails">Appointment details</h2><button class="dialog-close" type="button" data-close-dialog aria-label="Close">×</button></div><div class="dialog-content">
    <form id="appointment-edit-form"><input type="hidden" name="id"><div class="dialog-grid">
      <div class="field"><label for="edit-patient-name" data-i18n="admin.patientName">Patient name</label><input id="edit-patient-name" name="patient_name" maxlength="190" required></div>
      <div class="field"><label for="edit-patient-email" data-i18n="admin.email">Email</label><input id="edit-patient-email" name="patient_email" type="email" maxlength="190" required></div>
      <div class="field"><label for="edit-patient-phone" data-i18n="admin.phone">Phone</label><input id="edit-patient-phone" name="patient_phone" maxlength="40" required></div>
      <div class="field"><label for="edit-appointment-start" data-i18n="admin.appointmentDateTime">Date and time</label><input id="edit-appointment-start" name="appointment_start" type="datetime-local" step="1800" required></div>
      <div class="field full"><span class="secondary" id="appointment-meta"></span></div>
    </div><div class="form-actions"><button class="button" type="button" data-close-dialog data-i18n="admin.close">Close</button><button class="button primary" type="submit" data-i18n="admin.saveChanges">Save changes</button></div></form>
  </div></dialog>

  <dialog class="dialog" id="service-dialog"><div class="dialog-header"><h2 id="service-dialog-title" data-i18n="admin.addService">Add service</h2><button class="dialog-close" type="button" data-close-dialog aria-label="Close">×</button></div><div class="dialog-content"><form id="service-form"><input type="hidden" name="id"><div class="dialog-grid">
    <div class="field full"><label for="service-category" data-i18n="admin.serviceCategory">Category</label><input id="service-category" name="category" list="service-category-options" maxlength="100" required><datalist id="service-category-options"><option value="Preventiva i higijena"><option value="Konzervativa i endodoncija"><option value="Oralna hirurgija"><option value="Protetika"><option value="Implantologija"><option value="Estetska stomatologija"><option value="Ortodoncija"><option value="Parodontologija"></datalist></div>
    <div class="field full"><label for="service-name" data-i18n="admin.serviceName">Service name</label><input id="service-name" name="name" maxlength="190" required></div>
    <div class="field"><label for="service-duration" data-i18n="admin.durationMinutes">Duration (minutes)</label><input id="service-duration" name="duration_minutes" type="number" min="15" max="240" step="15" value="30" required></div>
    <label class="closed-toggle"><input name="active" type="checkbox" checked><span data-i18n="admin.activeService">Available for booking</span></label>
    <div class="field full"><label for="service-description" data-i18n="admin.description">Description</label><textarea id="service-description" name="description" maxlength="2000"></textarea></div>
  </div><div class="form-actions"><button class="button" type="button" data-close-dialog data-i18n="admin.close">Close</button><button class="button primary" type="submit" data-i18n="admin.saveService">Save service</button></div></form></div></dialog>

  <dialog class="dialog" id="content-dialog"><div class="dialog-header"><h2 id="content-dialog-title" data-i18n="admin.addContent">Add content</h2><button class="dialog-close" type="button" data-close-dialog aria-label="Close">×</button></div><div class="dialog-content"><form id="content-form"><input type="hidden" name="id"><div class="dialog-grid">
    <div class="field"><label for="content-type" data-i18n="admin.contentType">Content type</label><select id="content-type" name="content_type"><option value="dentist" data-i18n="admin.dentist">Dentist profile</option><option value="review" data-i18n="admin.review">Review</option><option value="gallery" data-i18n="admin.gallery">Gallery</option><option value="faq" data-i18n="admin.faq">FAQ</option></select></div>
    <div class="field"><label for="content-sort" data-i18n="admin.sortOrder">Sort order</label><input id="content-sort" name="sort_order" type="number" min="0" max="65535" value="0"></div>
    <div class="field full"><label for="content-title" data-i18n="admin.contentTitle">Title</label><input id="content-title" name="title" maxlength="190" required></div>
    <div class="field full"><label for="content-body" data-i18n="admin.body">Description / answer / quote</label><textarea id="content-body" name="body" maxlength="5000"></textarea></div>
    <div class="field full"><label for="content-media" data-i18n="admin.mediaUrl">Image URL (optional)</label><input id="content-media" name="media_url" maxlength="2048" placeholder="https://… or /images/…"></div>
    <label class="closed-toggle"><input name="is_published" type="checkbox"><span data-i18n="admin.publishContent">Published</span></label>
  </div><div class="form-actions"><button class="button danger" id="delete-content" type="button" hidden data-i18n="admin.deleteContent">Delete</button><button class="button" type="button" data-close-dialog data-i18n="admin.close">Close</button><button class="button primary" type="submit" data-i18n="admin.saveContent">Save content</button></div></form></div></dialog>

  <script>
    (() => {
      const csrf = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      const api = '../backend/admin_api.php';
      const $ = (selector, root = document) => root.querySelector(selector);
      const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
      const state = {
        i18n: null, clinic: null, appointments: [], services: [], content: [], activeAppointment: null,
        activeSection: 'overview', dashboardRequest: 0, appointmentsRequest: 0,
        lastRefreshAt: null, autoRefreshError: null
      };
      const autoRefreshInterval = 15000;
      const t = (key) => state.i18n?.t(key) || key;
      const apiErrors = {
        APPOINTMENT_TIME_UNAVAILABLE: 'admin.errorSlotBusy',
        APPOINTMENT_OUTSIDE_OPENING_HOURS: 'admin.errorOutsideHours',
        INVALID_APPOINTMENT_DATETIME: 'admin.errorDate',
        INVALID_APPOINTMENT_DETAILS: 'admin.errorAppointment',
        APPOINTMENT_NOT_EDITABLE: 'admin.errorNotEditable',
        STATUS_TRANSITION_NOT_ALLOWED: 'admin.errorStatus',
        CSRF_INVALID: 'admin.errorCsrf',
        INVALID_OPENING_HOURS: 'admin.errorHours',
        INVALID_CLINIC_PROFILE: 'admin.errorClinic',
        INVALID_SERVICE: 'admin.errorService',
        INVALID_CONTENT: 'admin.errorContent',
        DATABASE_ERROR: 'admin.errorDatabase',
        DATABASE_MIGRATION_REQUIRED: 'admin.errorDatabase',
      };
      function notify(message, error = false) {
        const flash = $('#flash');
        flash.textContent = message;
        flash.classList.toggle('error', error);
        flash.hidden = false;
        if (!error) window.setTimeout(() => { flash.hidden = true; }, 4500);
      }
      function renderAutoRefreshStatus() {
        const status = $('#auto-refresh-status');
        if (!status) return;
        const supportedSection = ['overview', 'appointments'].includes(state.activeSection);
        status.hidden = !supportedSection;
        if (!supportedSection) return;
        if (state.autoRefreshError) {
          status.textContent = t('admin.autoRefreshFailed').replace('{error}', state.autoRefreshError);
          return;
        }
        if (!state.lastRefreshAt) {
          status.textContent = t('admin.autoRefreshInfo');
          return;
        }
        const time = new Intl.DateTimeFormat(state.i18n?.getLocale() || 'sr-Latn', {
          hour: '2-digit', minute: '2-digit', second: '2-digit'
        }).format(state.lastRefreshAt);
        status.textContent = t('admin.autoRefreshUpdated').replace('{time}', time);
      }
      function markRefreshSuccess() {
        state.lastRefreshAt = new Date();
        state.autoRefreshError = null;
        renderAutoRefreshStatus();
      }
      function markRefreshFailure(error) {
        state.autoRefreshError = error instanceof Error ? error.message : t('admin.errorGeneric');
        renderAutoRefreshStatus();
      }
      function showPageError(message) {
        const fallback = $('#admin-shell-fallback');
        if (!fallback) return;
        fallback.hidden = false;
        fallback.innerHTML = '<strong>Admin dashboard could not be loaded.</strong><div>' + message + '</div>';
        $$('.section').forEach((section) => { section.style.display = 'none'; });
      }
      function apiMessage(code) { return apiErrors[code] ? t(apiErrors[code]) : t('admin.errorGeneric'); }
      async function request(url, options = {}) {
        const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', ...options, headers: {
          Accept: 'application/json',
          ...(options.body ? { 'Content-Type': 'application/json' } : {}),
          ...(options.method === 'POST' ? { 'X-CSRF-Token': csrf } : {}),
          ...(options.headers || {})
        }});
        if (response.status === 401) { window.location.href = 'login.php'; throw new Error(t('admin.errorSession')); }
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload.success) throw new Error(apiMessage(payload.message));
        return payload;
      }
      function setText(node, value) { node.textContent = value == null || value === '' ? '—' : String(value); }
      function setSection(name) {
        state.activeSection = name;
        renderAutoRefreshStatus();
        $$('.section').forEach(section => section.classList.toggle('active', section.id === `section-${name}`));
        $$('[data-section-link]').forEach(button => button.setAttribute('aria-current', String(button.dataset.sectionLink === name) === 'true' ? 'page' : 'false'));
        const title = $(`[data-section-link="${name}"] [data-i18n]`);
        if (title) document.title = `SmileCraft Dental — ${title.textContent}`;
        if (name === 'overview') loadDashboard();
        if (name === 'appointments') loadAppointments();
        if (name === 'services') loadServices();
        if (name === 'content') loadContent();
        if (name === 'clinic') loadClinic();
        history.replaceState(null, '', `#${name}`);
      }
      function metricCard(label, value, note = '') {
        const card = document.createElement('article');
        card.className = 'card metric';
        const labelEl = document.createElement('div'); labelEl.className = 'metric-label'; labelEl.textContent = label;
        const valueEl = document.createElement('div'); valueEl.className = 'metric-value'; valueEl.textContent = String(value ?? 0);
        const noteEl = document.createElement('div'); noteEl.className = 'metric-note'; noteEl.textContent = note;
        card.append(labelEl, valueEl, noteEl); return card;
      }
      async function loadDashboard({ background = false } = {}) {
        const requestId = ++state.dashboardRequest;
        try {
          const result = await request(`${api}?section=dashboard`);
          if (requestId !== state.dashboardRequest) return;
          state.clinic = result.clinic;
          const totals = result.totals || {};
          $('#metrics').replaceChildren(
            metricCard(t('admin.patients'), totals.patients, t('admin.patientMetricShort')),
            metricCard(t('admin.totalAppointments'), totals.appointments),
            metricCard(t('admin.awaitingConfirmation'), totals.pending),
            metricCard(t('admin.todayAppointments'), totals.today)
          );
          $('#welcome-copy').textContent = t('admin.welcomeClinic').replace('{clinic}', result.clinic?.name || '');
          renderTodayAppointments(result.today_appointments || []);
          markRefreshSuccess();
        } catch (error) {
          if (requestId !== state.dashboardRequest) return;
          if (background) {
            markRefreshFailure(error);
          } else {
            notify(error.message, true);
            showPageError(error.message);
          }
        }
      }
      function renderTodayAppointments(rows) {
        const list = $('#overview-message'); list.replaceChildren();
        if (!rows.length) {
          const empty = document.createElement('p'); empty.className = 'empty-state'; empty.textContent = t('admin.noAppointmentsToday'); list.append(empty); return;
        }
        for (const appointment of rows) {
          const row = document.createElement('div'); row.className = 'mini-row';
          const time = document.createElement('span'); time.className = 'mini-time'; time.textContent = String(appointment.appointment_start).slice(11, 16);
          const person = document.createElement('span'); person.className = 'mini-patient'; person.textContent = `${appointment.patient_name} · ${appointment.service_name || t('admin.generalDentistry')}`;
          const details = document.createElement('button'); details.type = 'button'; details.className = 'button small'; details.textContent = t('admin.details');
          details.addEventListener('click', () => openAppointment(appointment));
          row.append(time, person, details); list.append(row);
        }
      }
      function localDateTime(value) {
        const match = /^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})/.exec(value || '');
        return match ? `${match[1]}T${match[2]}` : '';
      }
      function statusText(status) {
        return t(status === 'confirmed' ? 'admin.confirmed' : status === 'cancelled' ? 'admin.cancelled' : 'admin.pending');
      }
      function makeCell(content) { const td = document.createElement('td'); if (content instanceof Node) td.append(content); else td.textContent = content; return td; }
      function renderAppointments(rows) {
        const body = $('#appointment-rows'); body.replaceChildren();
        if (!rows.length) {
          const tr = document.createElement('tr'); const td = makeCell(t('admin.empty')); td.colSpan = 5; td.className = 'empty-state'; tr.append(td); body.append(tr); return;
        }
        for (const appointment of rows) {
          const tr = document.createElement('tr');
          const patient = document.createElement('div'); patient.className = 'patient-primary'; patient.textContent = appointment.patient_name;
          const contact = document.createElement('span'); contact.className = 'secondary'; contact.textContent = appointment.patient_email || appointment.patient_phone;
          const patientWrap = document.createElement('div'); patientWrap.append(patient, contact);
          const service = document.createElement('div'); service.textContent = appointment.service_name || t('admin.generalDentistry');
          const duration = document.createElement('span'); duration.className = 'secondary'; duration.textContent = `${appointment.duration_minutes} ${t('admin.minutes')}`; service.append(duration);
          const date = document.createElement('div'); date.textContent = new Intl.DateTimeFormat(state.i18n?.getLocale() || 'en', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'UTC' }).format(new Date(`${appointment.appointment_start.replace(' ', 'T')}Z`));
          const status = document.createElement('span'); status.className = `pill ${appointment.status}`; status.textContent = statusText(appointment.status);
          const actions = document.createElement('div'); actions.className = 'row-actions';
          const details = document.createElement('button'); details.type = 'button'; details.className = 'button small'; details.textContent = t('admin.details'); details.addEventListener('click', () => openAppointment(appointment));
          actions.append(details);
          if (appointment.status === 'pending') actions.append(statusButton(appointment, 'confirmed'));
          if (appointment.status === 'pending' || appointment.status === 'confirmed') actions.append(statusButton(appointment, 'cancelled'));
          tr.append(makeCell(patientWrap), makeCell(service), makeCell(date), makeCell(status), makeCell(actions)); body.append(tr);
        }
      }
      function statusButton(appointment, status) {
        const button = document.createElement('button'); button.type = 'button'; button.className = `button small ${status === 'cancelled' ? 'danger' : 'subtle'}`;
        button.textContent = t(status === 'confirmed' ? 'admin.confirm' : 'admin.cancel');
        button.addEventListener('click', async () => {
          button.disabled = true;
          try { await request(api, { method: 'POST', body: JSON.stringify({ action: 'update_status', id: appointment.id, status }) }); notify(t('admin.statusSaved')); loadAppointments(); loadDashboard(); }
          catch (error) { notify(error.message, true); button.disabled = false; }
        });
        return button;
      }
      async function loadAppointments({ background = false } = {}) {
        const requestId = ++state.appointmentsRequest;
        const form = $('#appointment-filters');
        const params = new URLSearchParams({ section: 'appointments', status: form.elements.status.value });
        if (form.elements.date.value) params.set('date', form.elements.date.value);
        if (form.elements.search.value.trim()) params.set('search', form.elements.search.value.trim());
        if (!background) {
          $('#appointment-rows').innerHTML = `<tr><td class="loading-state" colspan="5">${t('admin.loading')}</td></tr>`;
        }
        try {
          const result = await request(`${api}?${params}`);
          if (requestId !== state.appointmentsRequest) return;
          state.appointments = result.appointments || [];
          renderAppointments(state.appointments);
          markRefreshSuccess();
        } catch (error) {
          if (requestId !== state.appointmentsRequest) return;
          if (background) {
            markRefreshFailure(error);
          } else {
            $('#appointment-rows').innerHTML = `<tr><td class="empty-state" colspan="5">${t('admin.loadFailed')}</td></tr>`;
            notify(error.message, true);
          }
        }
      }
      function refreshVisibleSection() {
        if (document.visibilityState !== 'visible' || $('#appointment-dialog').open) return;
        if (state.activeSection === 'overview') loadDashboard({ background: true });
        if (state.activeSection === 'appointments') loadAppointments({ background: true });
      }
      function openAppointment(appointment) {
        state.activeAppointment = appointment;
        const form = $('#appointment-edit-form');
        form.elements.id.value = appointment.id;
        form.elements.patient_name.value = appointment.patient_name || '';
        form.elements.patient_email.value = appointment.patient_email || '';
        form.elements.patient_phone.value = appointment.patient_phone || '';
        form.elements.appointment_start.value = localDateTime(appointment.appointment_start);
        $('#appointment-meta').textContent = `${t('admin.service')}: ${appointment.service_name || t('admin.generalDentistry')} · ${t('admin.status')}: ${statusText(appointment.status)}`;
        $$('#appointment-edit-form input').forEach(input => { input.disabled = appointment.status === 'cancelled'; });
        $('#appointment-edit-form button[type="submit"]').disabled = appointment.status === 'cancelled';
        $('#appointment-dialog').showModal();
      }
      function escapeText(value) { return String(value ?? ''); }
      async function loadServices() {
        try {
          const result = await request(`${api}?section=services`); state.services = result.services || [];
          const list = $('#service-list'); list.replaceChildren();
          if (!state.services.length) { list.append(emptyCard(t('admin.noServices'))); return; }
          const groups = new Map();
          for (const service of state.services) {
            const category = service.category || 'Ostalo';
            if (!groups.has(category)) groups.set(category, []);
            groups.get(category).push(service);
          }
          for (const [category, services] of groups) {
            const section = document.createElement('section'); section.className = 'service-category';
            const heading = document.createElement('h2'); heading.textContent = category;
            const cards = document.createElement('div'); cards.className = 'service-category-grid';
            for (const service of services) {
              const card = document.createElement('article'); card.className = 'card item-card';
              const top = document.createElement('div'); top.className = 'item-top';
              const title = document.createElement('h3'); title.textContent = service.name;
              const badge = document.createElement('span'); badge.className = `pill ${Number(service.active) ? 'active' : 'inactive'}`; badge.textContent = t(Number(service.active) ? 'admin.activeService' : 'admin.inactiveService');
              top.append(title, badge);
              const description = document.createElement('p'); description.textContent = service.description || t('admin.noDescription');
              const meta = document.createElement('div'); meta.className = 'item-meta';
              const length = document.createElement('span'); length.className = 'secondary'; length.textContent = `${service.duration_minutes} ${t('admin.minutes')}`; meta.append(length);
              const actions = document.createElement('div'); actions.className = 'card-actions';
              const edit = document.createElement('button'); edit.className = 'button small'; edit.type = 'button'; edit.textContent = t('admin.edit'); edit.addEventListener('click', () => openService(service));
              const toggle = document.createElement('button'); toggle.className = 'button small'; toggle.type = 'button'; toggle.textContent = t(Number(service.active) ? 'admin.deactivate' : 'admin.activate');
              toggle.addEventListener('click', () => saveService({ ...service, active: !Number(service.active) }));
              actions.append(edit, toggle); card.append(top, description, meta, actions); cards.append(card);
            }
            section.append(heading, cards); list.append(section);
          }
        } catch (error) { $('#service-list').replaceChildren(emptyCard(error.message)); }
      }
      function emptyCard(message) { const card = document.createElement('div'); card.className = 'card empty-state'; card.textContent = message; return card; }
      function openService(service = null) {
        const form = $('#service-form'); form.reset();
        form.elements.id.value = service?.id || '';
        form.elements.category.value = service?.category || 'Preventiva i higijena';
        form.elements.name.value = service?.name || '';
        form.elements.description.value = service?.description || '';
        form.elements.duration_minutes.value = service?.duration_minutes || 30;
        form.elements.active.checked = service ? Boolean(Number(service.active)) : true;
        $('#service-dialog-title').textContent = t(service ? 'admin.editService' : 'admin.addService');
        $('#service-dialog').showModal();
      }
      async function saveService(service) {
        try {
          await request(api, { method: 'POST', body: JSON.stringify({
            action: 'save_service', id: service.id || null, category: service.category, name: service.name,
            description: service.description || '', duration_minutes: Number(service.duration_minutes), active: Boolean(service.active)
          }) });
          $('#service-dialog').close(); notify(t('admin.serviceSaved')); loadServices();
        } catch (error) { notify(error.message, true); }
      }
      async function loadContent() {
        const type = $('#content-type-filter').value;
        try {
          const result = await request(`${api}?section=content&type=${encodeURIComponent(type)}`); state.content = result.items || [];
          const list = $('#content-list'); list.replaceChildren();
          if (!state.content.length) { list.append(emptyCard(t('admin.noContent'))); return; }
          for (const item of state.content) {
            const card = document.createElement('article'); card.className = 'card item-card';
            const top = document.createElement('div'); top.className = 'item-top';
            const title = document.createElement('h3'); title.textContent = item.title;
            const badge = document.createElement('span'); badge.className = `pill ${Number(item.is_published) ? 'published' : 'unpublished'}`; badge.textContent = t(Number(item.is_published) ? 'admin.published' : 'admin.draft');
            top.append(title, badge);
            const typeLabel = document.createElement('span'); typeLabel.className = 'secondary'; typeLabel.textContent = t(`admin.type.${item.content_type}`);
            const body = document.createElement('p'); body.textContent = item.body || item.media_url || t('admin.noDescription');
            const actions = document.createElement('div'); actions.className = 'card-actions';
            const edit = document.createElement('button'); edit.type = 'button'; edit.className = 'button small'; edit.textContent = t('admin.edit'); edit.addEventListener('click', () => openContent(item));
            actions.append(edit); card.append(top, typeLabel, body, actions); list.append(card);
          }
        } catch (error) { $('#content-list').replaceChildren(emptyCard(error.message)); }
      }
      function openContent(item = null) {
        const form = $('#content-form'); form.reset();
        form.elements.id.value = item?.id || '';
        form.elements.content_type.value = item?.content_type || ($('#content-type-filter').value === 'all' ? 'dentist' : $('#content-type-filter').value);
        if (item) form.elements.content_type.value = item.content_type;
        form.elements.title.value = item?.title || '';
        form.elements.body.value = item?.body || '';
        form.elements.media_url.value = item?.media_url || '';
        form.elements.sort_order.value = item?.sort_order ?? 0;
        form.elements.is_published.checked = Boolean(Number(item?.is_published || 0));
        $('#content-dialog-title').textContent = t(item ? 'admin.editContent' : 'admin.addContent');
        $('#delete-content').hidden = !item;
        $('#content-dialog').showModal();
      }
      async function loadClinic() {
        try { const result = await request(`${api}?section=clinic`); state.clinic = result.clinic; renderClinic(result.clinic); }
        catch (error) { notify(error.message, true); }
      }
      function renderClinic(clinic) {
        const form = $('#clinic-form');
        ['name', 'email', 'phone', 'address'].forEach(field => { form.elements[field].value = clinic[field] || ''; });
        const hours = $('#opening-hours'); hours.replaceChildren();
        const labels = ['admin.weekday.monday','admin.weekday.tuesday','admin.weekday.wednesday','admin.weekday.thursday','admin.weekday.friday','admin.weekday.saturday','admin.weekday.sunday'];
        for (const day of clinic.opening_hours || []) {
          const row = document.createElement('div'); row.className = 'hour-row';
          const name = document.createElement('span'); name.className = 'hour-day'; name.textContent = t(labels[Number(day.weekday) - 1]);
          const closed = document.createElement('label'); closed.className = 'closed-toggle';
          const checkbox = document.createElement('input'); checkbox.type = 'checkbox'; checkbox.name = `closed_${day.weekday}`; checkbox.checked = Boolean(Number(day.is_closed));
          const closedText = document.createElement('span'); closedText.textContent = t('admin.closed');
          closed.append(checkbox, closedText); row.append(name, closed);
          for (const [field, key] of [['opens_at','admin.opens'],['closes_at','admin.closes'],['break_starts_at','admin.breakStart'],['break_ends_at','admin.breakEnd']]) {
            const wrap = document.createElement('div'); wrap.className = 'field';
            const label = document.createElement('label'); label.textContent = t(key);
            const input = document.createElement('input'); input.type = 'time'; input.step = '1800'; input.name = `${field}_${day.weekday}`; input.value = day[field]?.slice(0,5) || '';
            input.setAttribute('aria-label', `${name.textContent} — ${t(key)}`);
            input.disabled = checkbox.checked; checkbox.addEventListener('change', () => { input.disabled = checkbox.checked; });
            wrap.append(label, input); row.append(wrap);
          }
          hours.append(row);
        }
      }
      function copyOpeningHours(targetWeekdays) {
        const form = $('#clinic-form');
        const sourceClosed = form.elements.closed_1.checked;
        const source = {};
        ['opens_at','closes_at','break_starts_at','break_ends_at'].forEach(field => {
          source[field] = form.elements[`${field}_1`].value;
        });
        targetWeekdays.forEach(weekday => {
          const closed = form.elements[`closed_${weekday}`];
          closed.checked = sourceClosed;
          closed.dispatchEvent(new Event('change'));
          ['opens_at','closes_at','break_starts_at','break_ends_at'].forEach(field => {
            form.elements[`${field}_${weekday}`].value = source[field];
          });
        });
        notify(t('admin.hoursCopied'));
      }
      function collectClinicForm(form) {
        const opening_hours = [];
        for (let weekday = 1; weekday <= 7; weekday++) {
          opening_hours.push({
            weekday, is_closed: form.elements[`closed_${weekday}`].checked,
            opens_at: form.elements[`opens_at_${weekday}`].value,
            closes_at: form.elements[`closes_at_${weekday}`].value,
            break_starts_at: form.elements[`break_starts_at_${weekday}`].value,
            break_ends_at: form.elements[`break_ends_at_${weekday}`].value
          });
        }
        return { name: form.elements.name.value, email: form.elements.email.value, phone: form.elements.phone.value, address: form.elements.address.value, opening_hours };
      }
      function bind() {
        $$('[data-section-link]').forEach(button => button.addEventListener('click', () => setSection(button.dataset.sectionLink)));
        $$('[data-go]').forEach(button => button.addEventListener('click', () => setSection(button.dataset.go)));
        $$('[data-close-dialog]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
        $$('dialog').forEach(dialog => dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); }));
        $('#appointment-filters').addEventListener('submit', event => { event.preventDefault(); loadAppointments(); });
        $('#appointment-search').addEventListener('input', () => { clearTimeout(window.searchTimer); window.searchTimer = setTimeout(loadAppointments, 250); });
        $('#reset-appointment-filters').addEventListener('click', () => setTimeout(loadAppointments, 0));
        $('#appointment-edit-form').addEventListener('submit', async event => {
          event.preventDefault();
          const form = event.currentTarget;
          try {
            await request(api, { method: 'POST', body: JSON.stringify({
              action: 'update_appointment', id: Number(form.elements.id.value),
              patient_name: form.elements.patient_name.value, patient_email: form.elements.patient_email.value,
              patient_phone: form.elements.patient_phone.value, appointment_start: form.elements.appointment_start.value
            }) });
            $('#appointment-dialog').close(); notify(t('admin.appointmentSaved')); loadAppointments(); loadDashboard();
          } catch (error) { notify(error.message, true); }
        });
        $('#add-service').addEventListener('click', () => openService());
        $('#service-form').addEventListener('submit', event => { event.preventDefault(); const f = event.currentTarget; saveService({ id:f.elements.id.value || null, category:f.elements.category.value, name:f.elements.name.value, description:f.elements.description.value, duration_minutes:f.elements.duration_minutes.value, active:f.elements.active.checked }); });
        $('#add-content').addEventListener('click', () => openContent());
        $('#content-filters').addEventListener('change', loadContent);
        $('#content-form').addEventListener('submit', async event => {
          event.preventDefault(); const f = event.currentTarget;
          try {
            await request(api, { method:'POST', body:JSON.stringify({ action:'save_content', id:f.elements.id.value || null, content_type:f.elements.content_type.value, title:f.elements.title.value, body:f.elements.body.value, media_url:f.elements.media_url.value, sort_order:Number(f.elements.sort_order.value), is_published:f.elements.is_published.checked }) });
            $('#content-dialog').close(); notify(t('admin.contentSaved')); loadContent();
          } catch (error) { notify(error.message, true); }
        });
        $('#delete-content').addEventListener('click', async () => {
          const id = Number($('#content-form').elements.id.value);
          if (!id || !window.confirm(t('admin.confirmDeleteContent'))) return;
          try { await request(api, { method:'POST', body:JSON.stringify({ action:'delete_content', id }) }); $('#content-dialog').close(); notify(t('admin.contentDeleted')); loadContent(); }
          catch (error) { notify(error.message, true); }
        });
        $('#clinic-form').addEventListener('submit', async event => {
          event.preventDefault();
          try { await request(api, { method:'POST', body:JSON.stringify({ action:'save_clinic', ...collectClinicForm(event.currentTarget) }) }); notify(t('admin.clinicSaved')); loadDashboard(); }
          catch (error) { notify(error.message, true); }
        });
        $('#copy-weekday-hours').addEventListener('click', () => copyOpeningHours([1, 2, 3, 4, 5]));
        $('#copy-all-hours').addEventListener('click', () => copyOpeningHours([1, 2, 3, 4, 5, 6, 7]));
        const language = $('[data-language-select]');
        document.addEventListener('smilecraft:localechange', () => {
          if (language && state.i18n) language.value = state.i18n.getLocale();
          renderAutoRefreshStatus();
          const active = location.hash.slice(1) || 'overview';
          if (active === 'overview') loadDashboard();
          if (active === 'appointments') renderAppointments(state.appointments);
          if (active === 'clinic' && state.clinic) renderClinic(state.clinic);
          if (active === 'services') loadServices();
          if (active === 'content') loadContent();
        });
        language?.addEventListener('change', () => state.i18n?.setLocale(language.value));
      }
      document.addEventListener('DOMContentLoaded', async () => {
        try {
          state.i18n = window.smilecraftI18n;
          await state.i18n?.ready;
          const language = $('[data-language-select]');
          if (language && state.i18n) language.value = state.i18n.getLocale();
          bind();
          const start = ['overview','appointments','services','content','clinic'].includes(location.hash.slice(1)) ? location.hash.slice(1) : 'overview';
          setSection(start);
          window.setInterval(refreshVisibleSection, autoRefreshInterval);
          document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') refreshVisibleSection();
          });
        } catch (error) {
          console.error('Admin dashboard initialization failed:', error);
          showPageError(error instanceof Error ? error.message : 'The dashboard could not be initialized.');
        }
      });
    })();
  </script>
</body>
</html>
