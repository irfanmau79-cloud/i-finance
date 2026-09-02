{{--
    Gaya modul Data Gaji & Tunjangan - diadopsi apa adanya dari GAS
    (index.html, blok "MODUL DATA GAJI & TUNJANGAN", "FORM CETAK RINCIAN
    PENGHASILAN", dan .gtd-del).

    Nilai warna sengaja dipertahankan persis seperti di GAS supaya tampilan
    mode terang identik dengan aplikasi lama. GAS tidak punya mode gelap,
    jadi penyesuaiannya ditaruh di blok [data-tema="gelap"] paling bawah -
    pola yang sama dipakai layouts/partials/styles.blade.php.
--}}
<style>
  /* ===== MODUL DATA GAJI & TUNJANGAN ===== */
  /* Di GAS setiap menu adalah panel flex setinggi viewport. Laravel memuat
     satu halaman per menu, jadi tingginya dipatok di sini supaya kotak tabel
     tetap memenuhi layar dan header tabelnya ikut lengket seperti aslinya. */
  .gt-card{padding-top:4px;display:flex;flex-direction:column;min-height:calc(100vh - 168px);}
  .gt-toolbar{flex:0 0 auto;display:flex;flex-wrap:nowrap;gap:12px;align-items:flex-end;margin:14px 0 6px;}
  .gt-field{display:flex;flex-direction:column;gap:5px;flex:0 0 auto;}
  .gt-field label{font-size:11px;font-weight:700;letter-spacing:.3px;text-transform:uppercase;color:var(--mut,var(--mut));margin:0;}
  .gt-field-search{flex:1 1 auto;min-width:120px;}
  .gt-inp{height:40px;border:1.5px solid var(--line,var(--line));border-radius:11px;padding:0 13px;font-size:13.5px;color:var(--tegas);background:var(--surface);outline:none;transition:border-color .15s,box-shadow .15s;font-family:inherit;}
  select.gt-inp{cursor:pointer;min-width:130px;}
  .gt-inp:focus{border-color:var(--navy);box-shadow:0 0 0 3px rgba(21,49,74,.10);}
  .gt-search{position:relative;display:flex;align-items:center;}
  .gt-search svg{position:absolute;left:12px;width:16px;height:16px;stroke:var(--mut,#94a3b8);fill:none;stroke-width:2;stroke-linecap:round;pointer-events:none;}
  .gt-search .gt-inp{width:100%;padding-left:36px;}
  .gt-btn-tampil{flex:0 0 auto;height:40px;padding:0 22px;border:none;border-radius:11px;background:var(--navy);color:#fff;font-size:13.5px;font-weight:700;cursor:pointer;transition:background .15s,transform .05s;box-shadow:0 2px 8px rgba(21,49,74,.18);font-family:inherit;}
  .gt-btn-tampil:hover{background:var(--navy-d);}
  .gt-btn-tampil:active{transform:translateY(1px);}
  .gt-info{flex:0 0 auto;font-size:12.5px;color:var(--mut,var(--mut));margin:2px 2px 10px;min-height:16px;}
  .gt-tabel-box{flex:1;min-height:0;overflow:auto;border:1.5px solid var(--line,var(--line));border-radius:14px;background:var(--surface);box-shadow:0 1px 3px rgba(15,39,64,.05);}
  .gt-tabel-wrap{min-width:820px;}
  .gt-empty{padding:34px 24px;text-align:center;color:var(--mut,var(--mut));font-size:13.5px;}
  .gt-table{border-collapse:separate;border-spacing:0;width:100%;font-variant-numeric:tabular-nums;}
  .gt-table-total thead th{font-size:11.5px;padding:8px 7px;}
  .gt-table-total tbody td{font-size:11.5px;padding:7px 7px;}
  .gt-table-total .gt-num{font-size:11.5px;}
  .gt-tabel-wrap:has(.gt-table-total){min-width:1180px;}
  .gt-table thead th{position:sticky;top:0;z-index:2;background:var(--navy);color:#fff;padding:9px 11px;font-size:11px;font-weight:600;text-align:center;vertical-align:middle;border-right:1px solid rgba(255,255,255,.10);border-bottom:2px solid var(--navy-d);}
  .gt-table thead tr:first-child th{border-top:none;}
  .gt-table thead th:last-child{border-right:none;}
  .gt-table tbody td{padding:8px 11px;font-size:12.5px;border-bottom:1px solid var(--surface-3);border-right:1px solid var(--surface-3);vertical-align:top;color:var(--ink);}
  .gt-table tbody td:last-child{border-right:none;}
  .gt-table tbody tr:nth-child(even){background:var(--surface-2);}
  .gt-table tbody tr:hover{background:var(--navy-l);}
  .gt-table tbody tr:last-child td{border-bottom:none;}
  .gt-num{text-align:right;white-space:nowrap;}
  .gt-ctr{text-align:center;white-space:nowrap;}
  .gt-peg{min-width:150px;max-width:210px;}
  .gt-peg .n{font-weight:700;color:var(--tegas);font-size:12.5px;line-height:1.25;}
  .gt-peg .m{font-size:10.5px;color:var(--mut);line-height:1.35;margin-top:1px;}
  .gt-strong{font-weight:700;color:var(--tegas);}
  .gt-pill{display:inline-block;padding:2px 9px;border-radius:50px;font-size:11.5px;font-weight:700;background:var(--navy-l);color:var(--tegas);}
  .gt-pager{flex:0 0 auto;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:12px;flex-wrap:wrap;}
  /* Di GAS tiap tombol halaman adalah <button> ber-onclick; di Laravel
     paginasinya berupa tautan, jadi <a> ikut memakai gaya yang sama. */
  .gt-pager button,.gt-pager a{min-width:36px;height:36px;padding:0 12px;border:1.5px solid var(--line,var(--line));background:var(--surface);border-radius:10px;font-size:13px;font-weight:600;color:var(--tegas);cursor:pointer;transition:all .15s;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;font-family:inherit;}
  .gt-pager a:hover,.gt-pager button:hover:not(:disabled){background:var(--navy-l);border-color:var(--navy);}
  .gt-pager button:disabled{opacity:.4;cursor:not-allowed;}
  .gt-pager button.active,.gt-pager a.active{background:var(--navy);color:#fff;border-color:var(--navy);}
  .gt-pager .gt-pg-info{font-size:12.5px;color:var(--mut,var(--mut));margin:0 4px;}
  @media(max-width:720px){
    .gt-toolbar{flex-wrap:wrap;}
    .gt-field-search{flex:1 1 100%;order:5;}
    .gt-btn-tampil{flex:1 1 100%;order:6;}
  }

  /* ===== FORM CETAK RINCIAN PENGHASILAN ===== */
  .gtc-form{margin-top:16px;display:flex;flex-direction:column;gap:16px;}
  .gtc-row{display:flex;flex-direction:column;gap:6px;}
  .gtc-row2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
  .gtc-inp{height:40px;border:1.5px solid var(--line,var(--line));border-radius:11px;padding:0 13px;font-size:13.5px;color:var(--tegas);background:var(--surface);outline:none;transition:border-color .15s,box-shadow .15s;font-family:inherit;width:100%;box-sizing:border-box;}
  .gtc-inp:focus{border-color:var(--navy);box-shadow:0 0 0 3px rgba(21,49,74,.10);}
  select.gtc-inp{cursor:pointer;}
  .gtc-search-wrap{position:relative;}
  .gtc-suggest{position:absolute;top:44px;left:0;right:0;background:var(--surface);border:1.5px solid var(--line,var(--line));border-radius:12px;box-shadow:0 8px 24px rgba(15,39,64,.12);max-height:260px;overflow:auto;z-index:30;}
  .gtc-suggest .it{padding:9px 13px;cursor:pointer;border-bottom:1px solid var(--surface-3);}
  .gtc-suggest .it:last-child{border-bottom:none;}
  .gtc-suggest .it:hover,.gtc-suggest .it.active{background:var(--navy-l);}
  .gtc-suggest .it .n{font-weight:700;color:var(--tegas);font-size:13px;}
  .gtc-suggest .it .m{font-size:11px;color:var(--mut);}
  .gtc-suggest .none{padding:12px 13px;color:#94a3b8;font-size:12.5px;}
  .gtc-bulan{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;}
  @media(max-width:560px){.gtc-bulan{grid-template-columns:repeat(3,1fr);}.gtc-row2{grid-template-columns:1fr;}}
  .gtc-chip{display:flex;align-items:center;gap:7px;padding:8px 11px;border:1.5px solid var(--line,var(--line));border-radius:10px;font-size:12.5px;cursor:pointer;user-select:none;transition:all .12s;color:var(--ink);}
  .gtc-chip:hover{border-color:var(--navy);}
  .gtc-chip.on{background:var(--navy);border-color:var(--navy);color:#fff;font-weight:600;}
  .gtc-chip input{display:none;}
  .gtc-toggle-line{display:flex;align-items:center;gap:10px;font-size:13.5px;color:var(--tegas);font-weight:600;}
  .gtc-switch{position:relative;display:inline-block;width:44px;height:24px;flex:0 0 44px;}
  .gtc-switch input{opacity:0;width:0;height:0;}
  .gtc-slider{position:absolute;inset:0;background:var(--surface-3);border-radius:50px;transition:.2s;cursor:pointer;}
  .gtc-slider:before{content:"";position:absolute;height:18px;width:18px;left:3px;top:3px;background:var(--surface);border-radius:50%;transition:.2s;}
  .gtc-switch input:checked + .gtc-slider{background:var(--navy);}
  .gtc-switch input:checked + .gtc-slider:before{transform:translateX(20px);}
  .gtc-actions{display:flex;gap:14px;align-items:center;margin-top:4px;}
  .gtc-pd-list{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;}
  @media(max-width:640px){.gtc-pd-list{grid-template-columns:repeat(2,1fr);}}
  .gtc-pd-item{display:flex;flex-direction:column;gap:4px;}
  .gtc-pd-item label{font-size:12px;font-weight:600;color:var(--tegas);margin:0;}
  .gtd-del{padding:6px 14px;border:1.5px solid #e05252;background:var(--surface);color:var(--err);border-radius:9px;font-size:12.5px;font-weight:600;cursor:pointer;transition:all .15s;}
  .gtd-del:hover{background:#c0392b;color:#fff;border-color:#c0392b;}

  /* ===== Penyesuaian mode gelap (tidak ada di GAS) ===== */
  :root[data-tema="gelap"] .gt-inp,
  :root[data-tema="gelap"] .gtc-inp,
  :root[data-tema="gelap"] .gt-tabel-box,
  :root[data-tema="gelap"] .gtc-suggest,
  :root[data-tema="gelap"] .gt-pager button,
  :root[data-tema="gelap"] .gt-pager a{background:var(--surface);color:var(--ink);border-color:var(--line);}
  :root[data-tema="gelap"] .gt-table tbody td{color:var(--ink);border-bottom-color:var(--line);border-right-color:var(--line);}
  :root[data-tema="gelap"] .gt-table tbody tr:nth-child(even){background:var(--surface-2);}
  :root[data-tema="gelap"] .gt-table tbody tr:hover{background:var(--navy-l);}
  :root[data-tema="gelap"] .gt-peg .n,
  :root[data-tema="gelap"] .gt-strong{color:var(--ink);}
  :root[data-tema="gelap"] .gt-peg .m,
  :root[data-tema="gelap"] .gtc-suggest .it .m{color:var(--mut);}
  :root[data-tema="gelap"] .gt-pill{background:var(--navy-l);color:var(--ink);}
  :root[data-tema="gelap"] .gtc-chip{color:var(--ink);border-color:var(--line);}
  :root[data-tema="gelap"] .gtc-chip.on{background:var(--navy-l);border-color:var(--navy-l);color:var(--ink);}
  :root[data-tema="gelap"] .gtc-suggest .it .n{color:var(--ink);}
  :root[data-tema="gelap"] .gtd-del{background:var(--surface);}
</style>
