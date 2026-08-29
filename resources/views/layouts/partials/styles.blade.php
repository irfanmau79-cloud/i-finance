<style>
  :root{
    --navy:#15314a; --navy-d:#0e2335; --navy-l:#e9eef3;
    --gold:#d9a938; --gold-d:#b98e28;
    --ink:#1f2937; --mut:#64748b; --line:#e5e9f0;
    --ok:#0f6e56; --ok-bg:#e8f5ee; --warn:#b07d1d; --warn-bg:#fbf3e2; --err:#b3261e; --err-bg:#fdecea;
    --radius:16px; --radius-sm:10px;
    --shadow:0 1px 2px rgba(15,23,42,.04),0 6px 20px rgba(15,23,42,.05);
    --shadow-hover:0 2px 4px rgba(15,23,42,.06),0 12px 30px rgba(15,23,42,.09);
    /* Warna permukaan dipisahkan jadi token supaya mode gelap cukup menukar
       nilainya di sini, bukan menyisir ulang tiap halaman. */
    --bg:#f1f5f9; --surface:#fff; --surface-2:#f7f9fc;
    /* Bilah atas: berangkat dari warna sidebar lalu MEMUDA ke kanan, sehingga
       tampak mengalir keluar dari sidebar alih-alih jadi balok navy kedua. */
    --tb-kiri:#15314a; --tb-kanan:#265479;
  }
  /* MODE GELAP. Token di bawah menutup rangka aplikasi dan permukaan umum
     (kartu, tabel, isian). Halaman yang masih memakai warna heksadesimal
     langsung - mis. #fff di dalam <style> per halaman - belum ikut berubah
     dan perlu disisir menyusul. */
  :root[data-tema="gelap"]{
    --navy:#16283c; --navy-d:#0d1b2a; --navy-l:#22374d;
    --ink:#e4ebf2; --mut:#93a4b8; --line:#2a3a4d;
    --bg:#0f1826; --surface:#16212f; --surface-2:#1b2836;
    --ok-bg:#12332a; --warn-bg:#33290f; --err-bg:#3a1c19;
    --tb-kiri:#16283c; --tb-kanan:#20405e;
    --shadow:0 1px 2px rgba(0,0,0,.4),0 6px 20px rgba(0,0,0,.35);
    --shadow-hover:0 2px 4px rgba(0,0,0,.45),0 12px 30px rgba(0,0,0,.5);
  }
  :root[data-tema="gelap"] body{background:var(--bg);}
  :root[data-tema="gelap"] .dash-card,
  :root[data-tema="gelap"] .card,
  :root[data-tema="gelap"] .inv-stat{background:var(--surface);border-color:var(--line);}
  :root[data-tema="gelap"] table.realisasi th{background:var(--surface-2);}
  :root[data-tema="gelap"] table.realisasi tbody tr:nth-child(even){background:rgba(255,255,255,.02);}
  :root[data-tema="gelap"] table.realisasi tbody tr:hover{background:rgba(255,255,255,.05);}
  :root[data-tema="gelap"] input,
  :root[data-tema="gelap"] select,
  :root[data-tema="gelap"] textarea{background:var(--surface-2);color:var(--ink);border-color:var(--line);}
  :root[data-tema="gelap"] .btn{background:var(--surface-2);color:var(--ink);border-color:var(--line);}
  :root[data-tema="gelap"] .btn.prim{background:var(--gold);color:#1b1408;}
  *{box-sizing:border-box;}
  body{margin:0;font-family:'Segoe UI',system-ui,-apple-system,Arial,sans-serif;
       background:#f1f5f9;color:var(--ink);font-size:14px;line-height:1.5;padding:12px 14px;}
  .wrap{max-width:760px;margin:0 auto;}
  .card{background:#fff;border-radius:var(--radius);box-shadow:0 1px 3px rgba(15,23,42,.08),0 1px 2px rgba(15,23,42,.04);overflow:hidden;}

  /* ===== Layout sidebar ===== */
  /* margin negatif membatalkan padding body agar sidebar benar-benar mepet ke tepi viewport, bukan kotak mengambang */
  .shell{max-width:none;margin:-12px -14px;display:flex;align-items:stretch;min-height:100vh;}
  .sidebar{width:255px;flex:0 0 auto;background:var(--navy);overflow:hidden;position:sticky;top:0;align-self:flex-start;height:100vh;display:flex;flex-direction:column;box-shadow:2px 0 14px rgba(15,23,42,.08);transition:width .22s ease;}
  .sb-menu{flex:1 1 auto;min-height:0;overflow-y:auto;overscroll-behavior:contain;}
  .sb-head{flex:0 0 auto;}
  #sb-userinfo{flex:0 0 auto;}
  .sb-logout{flex:0 0 auto;}
  /* Scrollbar tipis di sidebar */
  .sb-menu{scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.22) transparent;}
  .sb-menu::-webkit-scrollbar{width:6px;}
  .sb-menu::-webkit-scrollbar-track{background:transparent;}
  .sb-menu::-webkit-scrollbar-thumb{background:rgba(255,255,255,.20);border-radius:10px;}
  .sb-menu::-webkit-scrollbar-thumb:hover{background:rgba(255,255,255,.34);}
  .sb-head{padding:20px 18px 16px;border-bottom:1px solid rgba(255,255,255,.09);display:flex;align-items:center;gap:11px;}
  .sb-head .ic{width:46px;height:46px;flex:0 0 46px;display:flex;align-items:center;justify-content:center;}
  .sb-head .ic img{width:100%;height:100%;object-fit:contain;}
  .sb-head .t1{color:#fff;font-size:16px;font-weight:800;line-height:1.1;letter-spacing:-.2px;}
  .sb-head .t2{color:#9db8d6;font-size:11px;margin-top:3px;line-height:1.35;}
  .sb-menu{padding:12px 10px;}
  .sb-item{position:relative;display:flex;align-items:center;gap:11px;padding:11px 13px;border-radius:10px;color:#b9cbe0;cursor:pointer;font-size:14px;font-weight:500;margin-bottom:2px;transition:background .15s,color .15s;text-decoration:none;}
  .sidebar .sb-menu a.sb-item,
  .sidebar .sb-menu a.sb-item:visited,
  .sidebar .sb-menu a.sb-item:hover,
  .sidebar .sb-menu a.sb-item:focus,
  .sidebar .sb-menu a.sb-item:focus-visible,
  .sidebar .sb-menu a.sb-item:active,
  .sidebar .sb-menu a.sb-item.active{text-decoration:none;}
  .sb-item svg{width:19px;height:19px;flex:0 0 19px;stroke:currentColor;fill:none;stroke-width:1.9;}
  .sb-item:hover{background:rgba(255,255,255,.06);color:#fff;}
  .sb-item.active{background:rgba(255,255,255,.10);color:#fff;font-weight:600;}
  .sb-item.active::before{content:"";position:absolute;left:-10px;top:8px;bottom:8px;width:3px;border-radius:0 3px 3px 0;background:var(--gold);}
  .sb-item.active svg{color:var(--gold);}
  .sb-sep{height:1px;background:rgba(255,255,255,.10);margin:10px 12px;}
  .sb-parent{justify-content:flex-start;}
  .sb-parent .chev{width:15px;height:15px;margin-left:auto;flex:0 0 15px;transition:transform .18s;stroke:currentColor;stroke-width:2.2;fill:none;}
  .sb-group.open .sb-parent .chev{transform:rotate(90deg);}
  .sb-sub{max-height:0;overflow:hidden;transition:max-height .2s ease;}
  .sb-group.open .sb-sub{max-height:320px;}
  .sb-item.sub{padding-left:42px;font-size:13.5px;}
  .sb-item.sub::before{content:"";width:5px;height:5px;border-radius:50%;background:currentColor;opacity:.45;margin-right:9px;flex:0 0 5px;left:auto;top:auto;position:static;}
  .sb-item.sub.active::before{background:var(--gold);opacity:1;width:5px;height:5px;border-radius:50%;}
  /* Badge status */
  .badge{padding:4px 11px 4px 9px;border-radius:50px;font-weight:700;font-size:10.5px;white-space:nowrap;display:inline-flex;align-items:center;gap:5px;line-height:1.3;}
  .badge::before{content:"";width:6px;height:6px;border-radius:50%;background:currentColor;flex:none;opacity:.85;}
  .st-diterima{background:#f1f5f9;color:#475569;}
  .st-npd{background:#fee2e2;color:#991b1b;}          /* Draft NPD - PPTK  (merah) */
  .st-npd-bpp{background:#e0e7ff;color:#3730a3;}      /* Draft NPD - BPP   (indigo/biru) */
  .st-verifikasi{background:#fef3c7;color:#92400e;}
  .st-dikembalikan{background:#ffedd5;color:#c2410c;}
  .st-disetujui{background:#e0f2fe;color:#075985;}
  .st-selesai{background:#dcfce7;color:#166534;}
  .sp-link{display:inline-flex;align-items:center;gap:4px;margin-top:5px;padding:3px 10px 3px 8px;background:#eff6ff;color:#1d4ed8;border-radius:50px;font-size:10.5px;font-weight:600;white-space:nowrap;}
  .sp-link svg{width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2.2;}
  .pager{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:12px;flex-wrap:wrap;}
  .pager-info{font-size:12px;color:var(--mut);}
  .pager-btns{display:flex;gap:8px;}
  .pg-btn{padding:7px 14px;border:1px solid var(--line);background:#fff;border-radius:50px;font-size:12.5px;font-weight:600;color:var(--navy);cursor:pointer;transition:all .15s;text-decoration:none;display:inline-block;}
  .pg-btn:hover:not(:disabled){background:var(--navy-l);border-color:var(--navy);}
  .pg-btn:disabled{opacity:.4;cursor:not-allowed;}
  .wf-card{display:flex;flex-direction:column;}
  .wf-card .wf-scroll{margin-bottom:2px;}
  /* Wrapper tabel SP: selalu bisa scroll horizontal (tabel lebar 900px) di semua layar */
  .sp-table-wrap{overflow:auto;-webkit-overflow-scrolling:touch;min-width:0;max-width:100%;}
  /* Monitoring SP & Data SP: kunci tinggi 1 layar HANYA di desktop; di HP biarkan tumbuh & scroll normal */
  @media(min-width:841px){
    .wf-card{min-height:calc(100dvh - 24px);}
    #page-sp-monitor .wf-card{height:calc(100dvh - 24px);min-height:0;overflow:hidden;}
    #page-sp-monitor .sp-table-wrap{flex:1 1 auto;min-height:120px;}
    #page-sp-data .wf-card{height:calc(100dvh - 24px);min-height:0;overflow:hidden;}
    #page-sp-data .sp-table-wrap{flex:1 1 auto;min-height:120px;}
  }
  #page-sp-monitor .flow-grid{flex:0 0 auto;}
  #page-sp-monitor .pengumuman-box{flex:0 0 auto;}
  #page-sp-monitor #spi-table thead th{position:sticky;top:0;z-index:5;background:#fff;box-shadow:inset 0 -1px 0 var(--line);}
  #page-sp-data #spd-table thead th{position:sticky;top:0;z-index:5;background:#fff;box-shadow:inset 0 -1px 0 var(--line);}
  #page-sp-data #spd-table td{vertical-align:top;word-wrap:break-word;overflow-wrap:break-word;}
  .pengumuman-box{margin-top:14px;border:1px solid var(--line);border-left:3px solid var(--navy);border-radius:10px;background:linear-gradient(180deg,#fbfcfe,#f6f8fb);padding:12px 15px;}
  .pengumuman-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:6px;}
  .pengumuman-title{display:flex;align-items:center;gap:7px;font-size:12.5px;font-weight:700;color:var(--navy);letter-spacing:.2px;}
  .pengumuman-edit-btn{padding:4px 13px;border:1px solid var(--navy);background:#fff;color:var(--navy);border-radius:50px;font-size:11.5px;font-weight:600;cursor:pointer;transition:all .15s;}
  .pengumuman-edit-btn:hover{background:var(--navy);color:#fff;}
  .pengumuman-isi{font-size:13px;color:var(--ink);line-height:1.55;white-space:pre-wrap;word-wrap:break-word;}
  .pengumuman-kosong{color:var(--mut);opacity:.55;font-style:italic;}
  .pengumuman-edit-area{width:100%;border:1px solid var(--line);border-radius:8px;padding:9px 11px;font-size:13px;font-family:inherit;line-height:1.5;resize:vertical;min-height:70px;box-sizing:border-box;}
  .pengumuman-edit-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:8px;}
  /* Modal generik */
  .mdl-ov{display:none;position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:200;align-items:center;justify-content:center;padding:16px;}
  .mdl-ov.show{display:flex;}
  .mdl{background:#fff;border-radius:14px;max-width:460px;width:100%;max-height:90vh;overflow:auto;box-shadow:0 20px 50px rgba(0,0,0,.25);}
  .mdl-h{padding:18px 20px 6px;font-size:16px;font-weight:700;color:var(--navy);}
  .mdl-b{padding:6px 20px 18px;}
  .mdl-f{padding:12px 20px 18px;display:flex;gap:10px;justify-content:flex-end;}
  .wf-btn{border:none;border-radius:8px;padding:7px 12px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;margin:2px;}
  .wf-teruskan{background:#e0f2fe;color:#075985;}
  .wf-verif{background:#dcfce7;color:#166534;}
  .wf-setuju{background:#dbeafe;color:#1e40af;}
  .wf-selesai{background:#166534;color:#fff;}
  .wf-kembali{background:#ffedd5;color:#c2410c;}
  .wf-lihat{background:#f1f5f9;color:#334155;}
  .wf-btn:disabled{opacity:.5;cursor:not-allowed;}
  .catatan-box{background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:8px 11px;font-size:12px;color:#9a3412;margin-top:6px;}
  /* Sel Status terpadu (status + link SP + penanda catatan) */
  .stat-cell{display:flex;flex-direction:column;gap:6px;align-items:flex-start;border:1px solid var(--line);border-radius:10px;padding:8px 9px;background:#f5f7f9;min-width:0;}
  .pen-nm{font-weight:700;color:#a63f17;line-height:1.3;}
  .pen-sub{font-size:12px;color:var(--mut);margin-top:1px;}
  /* Pill mengikuti isinya (bukan 100%) agar sudut bulatnya utuh & tidak gepeng */
  .stat-badge, .stat-sp{width:auto;max-width:100%;min-width:0;}
  /* Badge di sel status: dikompres agar label terpanjang ("Verifikasi - Verifikator") tetap 1 baris */
  .stat-cell .badge{max-width:100%;white-space:nowrap;text-align:left;border-radius:50px;padding:4px 8px 4px 7px;gap:4px;font-size:9.6px;letter-spacing:-.1px;line-height:1.3;align-items:center;}
  .stat-cell .badge::before{width:5px;height:5px;flex:0 0 5px;}
  .stat-cell .badge::before{margin-top:1px;align-self:center;}
  .stat-cell .stat-cat-chip{max-width:100%;white-space:nowrap;font-size:10px;padding:3px 7px;}
  .stat-cat-chip{display:inline-flex;align-items:center;gap:5px;background:#fbf3e2;border:1px solid #f0dcae;border-radius:8px;padding:3px 9px;font-size:11px;font-weight:600;color:#b07d1d;white-space:nowrap;}
  .stat-cat-chip svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2;flex:0 0 12px;}
  /* Modal riwayat catatan timeline */
  .cat-timeline{display:flex;flex-direction:column;}
  .cat-row{display:flex;gap:11px;padding-bottom:14px;position:relative;}
  .cat-row:not(:last-child)::before{content:"";position:absolute;left:5px;top:14px;bottom:0;width:2px;background:var(--line);}
  .cat-dot{flex:0 0 12px;width:12px;height:12px;border-radius:50%;background:var(--gold);margin-top:3px;z-index:1;}
  .cat-body{flex:1;}
  .cat-aksi{font-size:12px;font-weight:700;color:var(--navy);}
  .cat-note{font-size:13px;color:var(--ink);margin:2px 0 3px;line-height:1.4;word-break:break-word;}
  .cat-meta{font-size:11px;color:var(--mut);}
  /* Pengajuan multi-select inline */
  .peng-cell{position:relative;}
  .peng-chips{display:flex;flex-wrap:wrap;gap:3px;min-height:20px;}
  .peng-chip{display:inline-block;background:#dcfce7;color:#166534;border-radius:6px;padding:2px 8px;font-size:11px;font-weight:600;white-space:nowrap;}
  .peng-chips-empty{color:var(--mut);font-size:12px;}
  .peng-trigger{border:1px solid var(--line);border-radius:7px;padding:4px 9px;font-size:11.5px;cursor:pointer;background:#f8fafc;color:var(--navy);font-weight:600;display:inline-flex;justify-content:space-between;align-items:center;gap:6px;min-width:130px;}
  .peng-trigger:hover{background:var(--navy-l);border-color:var(--navy);}
  .peng-trigger.ro{cursor:default;background:#f8fafc;}
  .peng-menu{display:none;position:absolute;top:calc(100% + 4px);left:0;background:#fff;border:1px solid var(--line);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.15);padding:6px;z-index:120;min-width:180px;}
  .peng-menu.show{display:block;}
  .peng-menu label{display:flex;align-items:center;gap:9px;padding:7px 9px;font-size:12.5px;cursor:pointer;border-radius:6px;}
  .peng-menu label:hover{background:var(--navy-l);}
  .peng-menu input[type=checkbox]{width:15px;height:15px;accent-color:#166534;cursor:pointer;}
  #spi-table td{vertical-align:top;padding-top:12px;padding-bottom:12px;}
  .st-aktif{background:#dcfce7;color:#166534;}
  .st-danger{background:#fee2e2;color:#991b1b;}
  /* Flow legend SP */
  .flow-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:18px;}
  .flow-card{background:#fff;border:1px solid var(--line);border-radius:11px;padding:13px;display:flex;gap:12px;align-items:flex-start;}
  .flow-card .num{min-width:27px;height:27px;background:var(--navy);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex:0 0 27px;}
  .flow-card b{font-size:12.5px;color:var(--navy);display:block;margin-bottom:3px;}
  .flow-card p{font-size:11px;color:var(--mut);margin:0;line-height:1.4;}
  @media(max-width:1024px){.flow-grid{grid-template-columns:repeat(2,1fr);}}
  @media(max-width:600px){.flow-grid{grid-template-columns:1fr;}}
  @media(max-width:440px){.kpi-grid{grid-template-columns:1fr;}}
  /* Toggle internal TK */
  .seg{display:inline-flex;background:var(--navy-l);border-radius:9px;padding:3px;gap:3px;margin-bottom:16px;}
  .seg button{border:none;background:transparent;padding:8px 16px;border-radius:7px;font-size:13px;font-weight:600;color:var(--navy);cursor:pointer;}
  .seg button.on{background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.1);}
  /* Tombol pilihan akses login */
  .auth-btn{display:flex;align-items:center;gap:13px;width:100%;text-align:left;border:1px solid var(--line);background:#fff;border-radius:12px;padding:14px;margin-bottom:12px;cursor:pointer;transition:border-color .15s,box-shadow .15s;}
  .auth-btn:hover{border-color:var(--navy);box-shadow:0 2px 8px rgba(15,39,64,.1);}
  .auth-btn .ab-ic{width:48px;height:48px;flex:0 0 48px;border-radius:50%;background:var(--navy);display:flex;align-items:center;justify-content:center;}
  .auth-btn .ab-ic svg{width:24px;height:24px;stroke:#fff;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round;}
  .auth-btn .ab-txt b{display:block;font-size:15px;color:var(--navy);}
  /* Tombol ikon aksi (daftar NPD) */
  .ic-btn{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:7px;border:1px solid var(--line);background:#fff;cursor:pointer;margin:0 2px;vertical-align:middle;transition:background .15s,border-color .15s;text-decoration:none;}
  .ic-btn svg{width:16px;height:16px;stroke:var(--navy);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
  .ic-btn:hover{background:var(--navy-l);border-color:var(--navy);}
  .ic-btn.danger svg{stroke:#dc2626;}
  .ic-btn.danger:hover{background:#fef2f2;border-color:#dc2626;}
  /* Bungkus ikon aksi agar rapi & bisa turun baris di kolom sempit */
  .aksi-wrap{display:grid;grid-template-columns:repeat(3,30px);gap:4px;justify-content:center;align-items:center;justify-items:center;width:102px;margin:0 auto;}
  .aksi-wrap .ic-btn{margin:0;}
  /* Sel tabel NPD: di DESKTOP boleh wrap (muat layar). Di HP pakai min-width + scroll (lihat media query). */
  @media(min-width:841px){
    #tbl-daftar-npd td, #prst-table td, #vrf-table td,
    #tbl-daftar-npd th, #prst-table th, #vrf-table th{ white-space:normal; word-break:break-word; overflow-wrap:anywhere; }
    #tbl-daftar-npd td.num, #prst-table td.num, #vrf-table td.num{ white-space:nowrap; }
  }
  /* Toggle Monitoring SP di Data SP */
  .sp-aksi{display:inline-flex;align-items:center;gap:6px;justify-content:center;flex-wrap:wrap;}
  .sp-toggle{display:inline-flex;align-items:center;gap:6px;margin-left:4px;}
  .sp-toggle-lbl{font-size:11px;color:var(--mut);font-weight:600;white-space:nowrap;}
  .sw{position:relative;display:inline-block;width:34px;height:19px;flex:0 0 34px;}
  .sw input{opacity:0;width:0;height:0;}
  .sw .sl{position:absolute;inset:0;background:#cbd5e1;border-radius:50px;transition:background .18s;cursor:pointer;}
  .sw .sl::before{content:"";position:absolute;height:15px;width:15px;left:2px;top:2px;background:#fff;border-radius:50%;transition:transform .18s;box-shadow:0 1px 2px rgba(0,0,0,.3);}
  .sw input:checked + .sl{background:var(--navy);}
  .sp-toggle.sumber .sw input:checked + .sl{background:var(--gold);}
  .sw input:checked + .sl::before{transform:translateX(15px);}
  .sw input:disabled + .sl{opacity:.5;cursor:default;}
  /* Histori timeline NPD */
  .hist-timeline{position:relative;padding-left:6px;}
  .hist-item{position:relative;display:flex;gap:12px;padding-bottom:16px;}
  .hist-item:not(:last-child)::before{content:"";position:absolute;left:13px;top:26px;bottom:0;width:2px;background:var(--line);}
  .hist-item.done:not(:last-child)::before{background:#bfe3d2;}
  .hist-dot{flex:0 0 26px;width:26px;height:26px;border-radius:50%;background:#eef2f7;color:var(--mut);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;z-index:1;}
  .hist-item.done .hist-dot{background:var(--ok);color:#fff;}
  .hist-content{flex:1;padding-top:2px;}
  .hist-label{font-size:13.5px;font-weight:600;color:var(--navy);}
  .hist-item:not(.done) .hist-label{color:var(--mut);font-weight:500;}
  .hist-meta{font-size:11.5px;color:var(--mut);margin-top:2px;}
  .hist-meta.belum{font-style:italic;opacity:.7;}
  @media(min-width:841px){
    #tbl-daftar-npd td, #prst-table td, #vrf-table td{word-wrap:break-word;overflow-wrap:break-word;white-space:normal;vertical-align:top;font-size:14px;}
    #tbl-daftar-npd th, #prst-table th, #vrf-table th{font-size:14px;}
  }
  /* Komponen search nama (dipakai di semua jenis NPD) */
  .nsearch{position:relative;}
  .nsearch .ns-inp{width:100%;padding:10px 12px 10px 34px;border:1px solid var(--line);border-radius:9px;font-size:14px;box-sizing:border-box;background:#fff;}
  .nsearch .ns-inp:focus{outline:none;border-color:var(--navy);box-shadow:0 0 0 3px rgba(15,39,64,.08);}
  .nsearch .ns-ic{position:absolute;left:11px;top:50%;transform:translateY(-50%);width:16px;height:16px;stroke:var(--mut);fill:none;stroke-width:2;pointer-events:none;}
  .nsearch .ns-drop{position:absolute;top:calc(100% + 4px);left:0;right:0;background:#fff;border:1px solid var(--line);border-radius:10px;box-shadow:0 8px 24px rgba(15,23,42,.13);max-height:240px;overflow:auto;z-index:40;display:none;}
  .nsearch .ns-drop.show{display:block;}
  .nsearch .ns-item{padding:9px 13px;font-size:13.5px;cursor:pointer;display:flex;align-items:center;gap:9px;border-bottom:1px solid #f1f5f9;}
  .nsearch .ns-item:last-child{border-bottom:none;}
  .nsearch .ns-item:hover,.nsearch .ns-item.hl{background:var(--navy-l);}
  .nsearch .ns-item .sub{font-size:11px;color:var(--mut);margin-left:auto;}
  /* Varian combobox: ikon panah di kanan supaya terbaca sebagai dropdown,
     bukan sekadar kotak pencarian. */
  .nsearch .ns-chev{position:absolute;right:11px;top:50%;transform:translateY(-50%);width:14px;height:14px;stroke:var(--mut);fill:none;stroke-width:2;stroke-linecap:round;cursor:pointer;transition:transform .15s;}
  .nsearch .ns-inp[aria-expanded="true"] ~ .ns-chev{transform:translateY(-50%) rotate(180deg);}
  .nsearch .ns-inp[role="combobox"]{padding-right:32px;cursor:pointer;}
  .nsearch .ns-item.ns-kosong{color:var(--mut);font-size:12px;cursor:default;}
  .nsearch .ns-item.ns-kosong:hover{background:transparent;}
  .nsearch .ns-item.manual{color:var(--navy);font-weight:600;border-bottom:1px solid var(--line);}
  .nsearch .ns-empty{padding:11px 13px;font-size:12.5px;color:var(--mut);text-align:center;}
  /* Logout di sidebar */
  .sb-logout{margin:6px 10px 12px;padding:11px 13px;border-radius:9px;color:#c4d4e6;cursor:pointer;font-size:13.5px;font-weight:500;display:flex;align-items:center;gap:10px;transition:background .15s;}
  .sb-logout:hover{background:rgba(255,255,255,.08);color:#fff;}
  .sb-logout svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:1.9;}
  /* Form grid 2 kolom */
  .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px 18px;margin-top:6px;}
  .form-grid .fg{display:flex;flex-direction:column;}
  .form-grid .fg.span2{grid-column:1 / -1;}
  .form-grid2{display:grid;grid-template-columns:1fr 1fr;gap:6px 12px;}
  @media(max-width:680px){.form-grid{grid-template-columns:1fr;}}
  /* Kartu anggota keluarga */
  .fam-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;}
  .fam-card{background:#fff;border:1px solid var(--line);border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(15,23,42,.05);}
  .fam-head{display:flex;align-items:center;gap:9px;font-weight:600;color:var(--navy);font-size:13.5px;margin-bottom:12px;}
  .fam-ic{width:26px;height:26px;flex:0 0 26px;background:var(--navy);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;}
  @media(max-width:900px){.fam-grid{grid-template-columns:1fr;}}
  .main{flex:1;min-width:0;padding:84px 14px 22px;}
  #page-npd .card{max-width:none;margin:0;display:flex;flex-direction:column;}
  @media(min-width:841px){
    #page-npd .card{min-height:calc(100vh - 34px);}
    .fullh-card{min-height:calc(100vh - 34px);max-height:calc(100vh - 34px);}
  }
  /* Toggle sembunyikan sidebar (desktop): tombol di header sidebar + tab mengambang saat tersembunyi */
  .sb-collapse{display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:7px;cursor:pointer;margin-left:auto;flex:0 0 30px;}
  .sb-collapse svg{width:18px;height:18px;stroke:#c4d4e6;stroke-width:2.2;fill:none;}
  .sb-collapse:hover{background:rgba(255,255,255,.1);}
  /* ===== SIDEBAR MENGECIL JADI REL IKON =====
     Tersembunyi bukan berarti hilang: lebarnya menyusut jadi 64px dan hanya
     menyisakan ikon. Logo di puncaknya menjadi tombol untuk membentangkannya
     kembali, begitu pula ikon mana pun di bawahnya. */
  @media(min-width:841px){
    html.sidebar-collapsed .sidebar{width:64px;}
    html.sidebar-collapsed .sb-head{padding:14px 0;justify-content:center;}
    html.sidebar-collapsed .sb-head .judul,
    html.sidebar-collapsed .sb-collapse,
    html.sidebar-collapsed .sb-item .chev,
    html.sidebar-collapsed .sb-sub,
    html.sidebar-collapsed #sb-userinfo,
    html.sidebar-collapsed .sb-logout span{display:none;}
    html.sidebar-collapsed .sb-head .ic{cursor:pointer;border-radius:10px;transition:background .15s;}
    html.sidebar-collapsed .sb-head .ic:hover{background:rgba(255,255,255,.12);}
    html.sidebar-collapsed .sb-menu{padding:12px 8px;}
    html.sidebar-collapsed .sb-item{justify-content:center;padding:11px 0;gap:0;}
    /* Teks menu disembunyikan tanpa menghapusnya dari DOM supaya pembaca
       layar dan test tetap menemukan namanya. */
    html.sidebar-collapsed .sb-item{font-size:0;}
    html.sidebar-collapsed .sb-item svg{font-size:14px;}
    html.sidebar-collapsed .sb-item.active::before{left:-8px;}
    html.sidebar-collapsed .sb-logout{justify-content:center;padding:11px 0;margin:6px 8px 12px;}
  }
  .sb-expand{display:none;}
  /* ===== Baris penyaring per kolom + kaki tabel =====
     Dipakai bersama oleh Data NPD, Pembuatan/Persetujuan/Verifikasi NPD, dan
     Tabel Rincian SPJ. Ditaruh di sini supaya keempatnya tidak berangsur beda. */
  table tr.kolom-saring th{padding:6px 8px;background:#fbfcfe;text-transform:none;letter-spacing:normal;position:static;}
  table tr.kolom-saring input{width:100%;box-sizing:border-box;border:1px solid var(--line);border-radius:7px;
    padding:6px 9px;font-family:inherit;font-size:12px;font-weight:400;color:var(--ink);background:#fff;
    transition:border-color .15s,box-shadow .15s;}
  table tr.kolom-saring input::placeholder{color:#a9b6c4;font-weight:400;}
  table tr.kolom-saring input:focus{outline:none;border-color:var(--navy);box-shadow:0 0 0 3px rgba(21,49,74,.1);}
  table tr.kolom-saring .saring-kosong{display:flex;justify-content:center;}
  table tr.kolom-saring button{border:1px solid var(--line);background:#fff;border-radius:7px;width:30px;height:30px;
    display:inline-flex;align-items:center;justify-content:center;color:var(--mut);cursor:pointer;transition:.15s;}
  table tr.kolom-saring button:hover{border-color:var(--navy);color:var(--navy);}
  table tr.kolom-saring button svg{width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;}
  .dn-kaki{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:12px 2px 0;}
  .dn-perpage{display:flex;align-items:center;gap:7px;font-size:12px;color:var(--mut);}
  .dn-perpage select{border:1px solid var(--line);border-radius:8px;padding:5px 8px;font-family:inherit;font-size:12px;background:#fff;}

  /* ===== KOMBO: dropdown yang bisa diketik untuk mencari =====
     Dipakai bersama oleh Inventarisasi SPJ (.inv-kombo) dan Dashboard
     Realisasi Anggaran (.kombo). Ditaruh di sini, bukan di masing-masing
     halaman, supaya rupanya tidak berangsur berbeda. Teks panjang dibiarkan
     turun baris di daftar pilihannya. */
  .kombo,.inv-kombo{position:relative;}
  .kombo .kb-inp,.inv-kombo .kb-inp{width:100%;box-sizing:border-box;background:#fff;border:1.5px solid var(--line);
    border-radius:9px;padding:9px 30px 9px 11px;font-family:inherit;font-size:13px;color:var(--ink);cursor:pointer;
    text-overflow:ellipsis;transition:border-color .15s,box-shadow .15s;}
  .kombo .kb-inp:focus,.inv-kombo .kb-inp:focus{outline:none;border-color:var(--navy);box-shadow:0 0 0 3px rgba(21,49,74,.1);cursor:text;}
  .kombo .kb-inp:disabled,.inv-kombo .kb-inp:disabled{background:#f1f5f9;color:var(--mut);cursor:not-allowed;}
  .kombo .kb-chev,.inv-kombo .kb-chev{position:absolute;right:10px;top:50%;transform:translateY(-50%);width:14px;height:14px;
    stroke:var(--mut);fill:none;stroke-width:2;stroke-linecap:round;pointer-events:none;transition:transform .15s;}
  .kombo.buka .kb-chev,.inv-kombo.buka .kb-chev{transform:translateY(-50%) rotate(180deg);}
  .kombo .kb-drop,.inv-kombo .kb-drop{position:absolute;top:calc(100% + 4px);left:0;right:0;background:#fff;
    border:1px solid var(--line);border-radius:10px;box-shadow:0 10px 26px rgba(15,23,42,.14);max-height:260px;
    overflow:auto;z-index:40;display:none;}
  .kombo.buka .kb-drop,.inv-kombo.buka .kb-drop{display:block;}
  .kombo .kb-item,.inv-kombo .kb-item{padding:9px 12px;font-size:12.5px;line-height:1.45;cursor:pointer;
    border-bottom:1px solid #f1f5f9;white-space:normal;overflow-wrap:anywhere;}
  .kombo .kb-item:last-child,.inv-kombo .kb-item:last-child{border-bottom:none;}
  .kombo .kb-item:hover,.kombo .kb-item.sorot,
  .inv-kombo .kb-item:hover,.inv-kombo .kb-item.sorot{background:var(--navy-l);}
  .kombo .kb-item.terpilih,.inv-kombo .kb-item.terpilih{font-weight:700;color:var(--navy);}
  .kombo .kb-kosong,.inv-kombo .kb-kosong{padding:12px;font-size:12px;color:var(--mut);text-align:center;}
  /* ===== BILAH ATAS TETAP =====
     Selalu terlihat saat halaman digulir. Karena position:fixed melepasnya
     dari alur dokumen, .main diberi padding-top setinggi bilahnya. */
  /* Bilah atas dimulai tepat di tepi kanan sidebar - lebar kirinya mengikuti
     keadaan sidebar (255px terbentang, 64px saat jadi rel ikon), dengan
     transisi yang sama supaya keduanya bergerak seiring. */
  .topbar{position:fixed;top:0;left:255px;right:0;z-index:70;display:flex;align-items:center;gap:14px;
    background:linear-gradient(90deg,var(--tb-kiri) 0%,var(--tb-kanan) 100%);
    padding:0 22px;height:72px;box-shadow:0 4px 18px rgba(15,23,42,.14);
    border-bottom:1px solid rgba(217,169,56,.28);transition:left .22s ease;}
  html.sidebar-collapsed .topbar{left:64px;}
  .topbar .burger{width:26px;height:26px;cursor:pointer;flex:0 0 26px;display:none;}
  .topbar .burger svg{width:100%;height:100%;stroke:#fff;stroke-width:2;fill:none;}

  /* Teks berjalan dengan efek ketik. Kursornya balok berkedip di ujung. */
  /* Bahnschrift = turunan DIN bawaan Windows: condensed, bergaya teknis
     seperti papan instrumen. Kalimat panjang jadi tetap muat pada satu baris,
     yang tidak tercapai dengan huruf monospasi. */
  .tb-ketik{flex:1 1 auto;min-width:0;color:#fff;font-size:21px;font-weight:600;letter-spacing:.9px;
    font-family:'Bahnschrift','DIN Alternate','Segoe UI Variable Display','Trebuchet MS','Segoe UI',sans-serif;
    text-shadow:0 0 18px rgba(217,169,56,.22);
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .tb-ketik .kursor{display:inline-block;width:3px;height:1.05em;background:var(--gold);margin-left:3px;
    vertical-align:-3px;box-shadow:0 0 8px rgba(217,169,56,.65);animation:tb-kedip 1s steps(1) infinite;}
  @keyframes tb-kedip{50%{opacity:0;}}

  .tb-kanan{display:flex;align-items:center;gap:10px;flex:0 0 auto;}
  .tb-tahun{color:#cfe0f2;font-size:13px;font-weight:600;white-space:nowrap;padding:7px 13px;
    border-radius:20px;background:rgba(255,255,255,.13);letter-spacing:.5px;
    font-family:'Bahnschrift','DIN Alternate','Segoe UI Variable Display','Trebuchet MS','Segoe UI',sans-serif;}
  .tb-ikon{width:38px;height:38px;flex:0 0 38px;border:none;border-radius:10px;background:rgba(255,255,255,.13);
    color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;padding:0;transition:background .15s;}
  .tb-ikon:hover{background:rgba(255,255,255,.24);}
  .tb-ikon svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;}

  /* Sakelar terang/gelap: matahari berputar keluar, bulan berputar masuk. */
  .tb-tema{position:relative;overflow:hidden;}
  .tb-tema svg{position:absolute;transition:transform .35s cubic-bezier(.4,0,.2,1),opacity .25s;}
  .tb-tema .bulan{transform:rotate(-90deg) scale(.4);opacity:0;}
  :root[data-tema="gelap"] .tb-tema .matahari{transform:rotate(90deg) scale(.4);opacity:0;}
  :root[data-tema="gelap"] .tb-tema .bulan{transform:none;opacity:1;}

  .tb-profil{position:relative;flex:0 0 auto;}
  .tb-avatar{width:38px;height:38px;border-radius:50%;border:1.5px solid rgba(255,255,255,.35);background:var(--gold);
    color:#1b1408;font-weight:800;font-size:13px;display:flex;align-items:center;justify-content:center;
    cursor:pointer;padding:0;transition:border-color .15s;}
  .tb-avatar:hover{border-color:#fff;}
  .tb-menu{position:absolute;top:calc(100% + 8px);right:0;min-width:200px;background:var(--surface);
    border:1px solid var(--line);border-radius:12px;box-shadow:0 12px 30px rgba(15,23,42,.22);
    padding:6px;display:none;z-index:80;}
  .tb-menu.buka{display:block;}
  .tb-menu .nm{padding:9px 11px 10px;border-bottom:1px solid var(--line);margin-bottom:4px;}
  .tb-menu .nm b{display:block;color:var(--ink);font-size:13px;}
  .tb-menu .nm span{color:var(--mut);font-size:11px;}
  .tb-menu a,.tb-menu button{display:flex;align-items:center;gap:9px;width:100%;padding:9px 11px;border:none;
    background:none;border-radius:8px;color:var(--ink);font:inherit;font-size:13px;text-align:left;
    text-decoration:none;cursor:pointer;}
  .tb-menu a:hover,.tb-menu button:hover{background:var(--navy-l);}
  .tb-menu svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2;}
  .page{display:none;}
  /* ===== INVENTARISASI SPJ ===== */
  .inv-muted{color:var(--mut);font-weight:400;font-size:12px;}
  .inv-stats{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;}
  .inv-stat{flex:1;min-width:140px;background:#fff;border:1px solid var(--line);border-radius:12px;padding:14px 16px;}
  .inv-stat .lbl{font-size:12px;color:var(--mut);}
  .inv-stat .val{font-size:22px;font-weight:800;color:var(--navy);margin-top:3px;}
  .inv-filter{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;background:#fff;border:1px solid var(--line);border-radius:12px;padding:14px 16px;margin-bottom:18px;}
  .inv-filter-grp{display:flex;flex-direction:column;gap:4px;flex:1 1 0;min-width:180px;}
  .inv-filter-grp.inv-filter-search{flex:1 1 100%;min-width:200px;}
  .inv-filter-row2{display:flex;gap:12px;align-items:flex-end;flex:1 1 100%;}
  .inv-filter-row2 .inv-filter-grp{flex:1 1 auto;}
  .inv-filter-grp select,.inv-filter-grp input{width:100%;padding:9px 11px;border:1px solid var(--line);border-radius:8px;font-size:13px;box-sizing:border-box;}
  .inv-filter-grp select{text-overflow:ellipsis;}
  .inv-rak-title,.inv-stack-title{font-size:15px;font-weight:700;color:var(--navy);}
  .inv-rak-wrap{margin-bottom:10px;}
  /* ===== RAK BANTEX (spine berjajar) ===== */
  .inv-rak{display:flex;flex-wrap:wrap;gap:6px;align-items:flex-end;margin-top:18px;
    background:linear-gradient(180deg,#eef1f6 0%,#e4e8ef 100%);border:1px solid var(--line);border-radius:14px;
    padding:18px 16px 0;box-shadow:inset 0 2px 10px rgba(16,38,66,.07);position:relative;}
  /* papan rak kayu di bawah */
  .inv-rak::after{content:"";position:absolute;left:0;right:0;bottom:0;height:14px;
    background:linear-gradient(180deg,#c39257,#a3773f);border-radius:0 0 13px 13px;
    box-shadow:inset 0 2px 5px rgba(0,0,0,.28),0 -1px 0 rgba(0,0,0,.12);}
  /* satu bantex = punggung tegak */
  .bantex{position:relative;width:70px;height:180px;margin-bottom:14px;cursor:pointer;
    border-radius:6px 6px 3px 3px;background:linear-gradient(100deg,#4d7fc4 0%,#2f62a8 18%,#2a5a9d 78%,#1f4881 100%);
    border:1px solid #1c4179;box-shadow:0 3px 6px rgba(16,38,66,.3),inset -3px 0 6px rgba(0,0,0,.18),inset 2px 0 3px rgba(255,255,255,.22);
    display:flex;flex-direction:column;align-items:center;padding-top:10px;transform-origin:center bottom;
    transition:transform .22s cubic-bezier(.22,1,.36,1),box-shadow .22s,filter .22s;}
  /* label kertas putih */
  .bantex .bx-label{width:52px;background:linear-gradient(180deg,#faf8f1,#efece1);border:1px solid #d9d4c4;
    border-radius:2px;padding:4px 3px 5px;text-align:center;box-shadow:0 1px 2px rgba(0,0,0,.18);}
  .bantex .bx-brand{background:#1d3557;color:#fff;font-size:8px;font-weight:700;letter-spacing:.4px;
    border-radius:1px;padding:1.5px 0;line-height:1.2;}
  .bantex .bx-arsip{font-size:6.5px;color:#6b675c;letter-spacing:.8px;margin-top:3px;font-weight:600;}
  .bantex .bx-no{font-size:15px;font-weight:800;color:#1d3557;line-height:1.05;}
  .bantex .bx-rule{height:1px;background:#d5cfbe;margin:3px 2px;}
  .bantex .bx-name{font-size:7.5px;font-weight:700;color:#2c2b26;line-height:1.25;min-height:19px;
    display:flex;align-items:center;justify-content:center;word-break:break-word;padding:0 1px;}
  .bantex .bx-meta{font-size:6.5px;color:#6b675c;font-weight:600;margin-top:2px;line-height:1.2;}
  .bantex .bx-count{font-size:7px;color:#3b5a80;font-weight:800;margin-top:1px;}
  /* lubang jari logam */
  .bantex .bx-hole{position:absolute;bottom:16px;left:50%;transform:translateX(-50%);
    width:22px;height:22px;border-radius:50%;background:radial-gradient(circle at 50% 35%,#16325c,#0b1e3a);
    border:2px solid #9db2ce;box-shadow:inset 0 3px 5px rgba(0,0,0,.7),0 1px 0 rgba(255,255,255,.2);}
  /* hover: bantex ditarik keluar rak */
  .bantex:hover{transform:translateY(-16px) scale(1.02);
    box-shadow:0 16px 24px -8px rgba(16,38,66,.45),inset -3px 0 6px rgba(0,0,0,.18);filter:brightness(1.1);}
  .bantex:active{transform:translateY(-8px) scale(1.0);}
  /* animasi zoom-in saat dibuka */
  .bantex.zooming{z-index:5;transform:translateY(-16px) scale(3);opacity:0;
    transition:transform .38s cubic-bezier(.5,0,.75,0),opacity .38s ease-in;}
  .bantex.kosong{filter:saturate(.25) brightness(1.06);opacity:.6;}
  @keyframes invZoomIn{from{opacity:0;transform:scale(.86);}to{opacity:1;transform:none;}}
  /* Level 2: tumpukan dokumen */
  .inv-stack-wrap{background:#fff;border:1px solid var(--line);border-radius:12px;padding:16px;margin-top:6px;animation:invZoomIn .3s cubic-bezier(.2,.8,.2,1);}
  .inv-doc-wrap{background:#fff;border:1px solid var(--line);border-radius:12px;padding:16px;margin-top:6px;animation:invFade .25s ease;}
  .inv-crumb{display:flex;align-items:center;gap:12px;margin-bottom:14px;}
  .inv-stack{display:flex;flex-wrap:wrap;gap:20px 18px;padding:10px 4px 6px;}
  /* MAP FOLDER KUNING + tumpukan kertas putih */
  .inv-paper{position:relative;width:158px;height:198px;cursor:pointer;
    transition:transform .22s cubic-bezier(.34,1.56,.64,1);transform-origin:center bottom;}
  /* map kuning (belakang) */
  .inv-paper .pp-folder{position:absolute;left:0;top:6px;width:150px;height:186px;border-radius:8px 8px 10px 8px;
    background:linear-gradient(150deg,#f0c35c 0%,#e6b247 55%,#d9a231 100%);
    box-shadow:0 3px 8px rgba(60,40,0,.28);}
  /* tab map di kanan */
  .inv-paper .pp-folder::after{content:"";position:absolute;right:-9px;top:56px;width:11px;height:52px;
    border-radius:0 5px 5px 0;background:linear-gradient(90deg,#e3ae40,#d49b28);
    box-shadow:2px 2px 4px rgba(60,40,0,.22);}
  /* lembar kertas: 3 lapis menyembul (paling belakang paling kanan) */
  .inv-paper .pp-sheet{position:absolute;border-radius:5px;background:#fff;border:1px solid #e2e5ea;
    box-shadow:0 1px 3px rgba(0,0,0,.10);}
  .inv-paper .pp-s3{left:16px;top:9px;width:138px;height:176px;background:#f4f5f7;}
  .inv-paper .pp-s2{left:10px;top:5px;width:138px;height:180px;background:#fafbfc;}
  /* lembar depan = kartu isi */
  .inv-paper .pp-front{left:2px;top:0;width:140px;height:184px;overflow:hidden;padding:12px 11px;
    box-shadow:0 3px 9px rgba(0,0,0,.14);}
  .inv-paper .pp-npd{font-size:11.5px;font-weight:800;color:var(--navy);margin-top:5px;word-break:break-word;line-height:1.25;}
  .inv-paper .pp-nom{font-size:13px;font-weight:800;color:#1a7f4b;margin-top:7px;}
  .inv-paper .pp-nama{font-size:10.5px;color:var(--mut);margin-top:5px;line-height:1.3;
    display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
  .inv-paper .pp-lines{position:absolute;left:11px;right:11px;bottom:11px;}
  .inv-paper .pp-lines i{display:block;height:3px;background:#eef1f5;border-radius:2px;margin-top:5px;}
  .inv-paper .pp-lines i:nth-child(2){width:80%;} .inv-paper .pp-lines i:nth-child(3){width:60%;}
  /* hover: kertas depan terangkat & kertas belakang mengipas */
  .inv-paper:hover{transform:translateY(-10px);}
  .inv-paper:hover .pp-front{transform:rotate(-2.5deg) translateX(-3px);box-shadow:0 14px 26px rgba(0,0,0,.22);}
  .inv-paper:hover .pp-s2{transform:rotate(1.5deg) translateX(3px);}
  .inv-paper:hover .pp-s3{transform:rotate(3.5deg) translateX(6px);}
  .inv-paper .pp-front,.inv-paper .pp-s2,.inv-paper .pp-s3{transition:transform .22s cubic-bezier(.34,1.56,.64,1),box-shadow .22s;}
  /* Level 3: kartu dokumen terbuka */
  .inv-doc-card{max-width:640px;margin:0 auto;background:#fff;border:1px solid var(--line);border-radius:10px;
    box-shadow:0 12px 30px rgba(0,0,0,.12);overflow:hidden;animation:invOpen .3s cubic-bezier(.2,.8,.2,1);transform-origin:top center;}
  .inv-doc-card .dc-head{background:linear-gradient(135deg,#1f3a5f,#2c5282);color:#fff;padding:16px 20px;}
  .inv-doc-card .dc-head .t{font-size:16px;font-weight:800;}
  .inv-doc-card .dc-head .s{font-size:12px;color:#cfe0f2;margin-top:3px;}
  .inv-doc-card .dc-body{padding:6px 20px 18px;}
  .inv-doc-row{display:flex;padding:10px 0;border-bottom:1px dashed var(--line);}
  .inv-doc-row:last-child{border-bottom:none;}
  .inv-doc-row .k{width:140px;flex-shrink:0;font-size:12px;color:var(--mut);font-weight:600;}
  .inv-doc-row .v{font-size:14px;color:#1a2430;font-weight:500;}
  .inv-doc-row .v.big{font-size:18px;font-weight:800;color:#1a7f4b;}
  @keyframes invFade{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:none;}}
  @keyframes invOpen{from{opacity:0;transform:scaleY(.6) translateY(-10px);}to{opacity:1;transform:none;}}
  .inv-table-wrap{margin-top:22px;}
  .inv-table-head{display:flex;align-items:center;gap:8px;cursor:pointer;font-size:15px;font-weight:700;color:var(--navy);padding:10px 0;user-select:none;}
  .inv-table-head.open #inv-tbl-chev{transform:rotate(90deg);}
  .inv-tbl-card{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden;box-shadow:0 6px 18px -10px rgba(16,38,66,.2);}
  .inv-tbl-scroll{overflow-x:auto;}
  table.inv-modtable{width:100%;border-collapse:separate;border-spacing:0;font-size:13px;}
  table.inv-modtable thead th{position:sticky;top:0;background:linear-gradient(180deg,#1f3a5f,#1a3355);color:#eaf1fa;
    text-align:left;font-size:11.5px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;padding:12px 14px;white-space:nowrap;}
  table.inv-modtable thead th.ta-r{text-align:right;}
  table.inv-modtable tbody td{padding:12px 14px;border-bottom:1px solid #eef1f5;color:#2a3746;vertical-align:top;}
  table.inv-modtable tbody tr{transition:background .12s;}
  table.inv-modtable tbody tr:hover{background:#f5f9ff;}
  table.inv-modtable tbody tr:last-child td{border-bottom:none;}
  table.inv-modtable td.ta-r{text-align:right;white-space:nowrap;font-weight:700;color:#14663c;font-variant-numeric:tabular-nums;}
  table.inv-modtable .badge-bulan{display:inline-block;background:#eef3fb;color:#26507f;font-size:13px;font-weight:700;padding:3px 9px;border-radius:20px;white-space:nowrap;}
  table.inv-modtable .badge-lok{display:inline-block;background:#fff5e2;color:#9a6b12;font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px;white-space:nowrap;}
  table.inv-modtable .cell-clip{white-space:normal;word-break:break-word;overflow-wrap:anywhere;min-width:110px;}
  table.inv-modtable td{vertical-align:top;}
  table.inv-modtable .cell-npd{font-weight:700;color:var(--navy);white-space:normal;word-break:break-word;}
  .inv-pager{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;background:#fafbfd;border-top:1px solid var(--line);flex-wrap:wrap;}
  .inv-pager .pg-info{font-size:12.5px;color:var(--mut);}
  .inv-pager .pg-btns{display:flex;gap:6px;align-items:center;flex-wrap:wrap;}
  .inv-pg{min-width:34px;height:34px;padding:0 10px;border:1px solid var(--line);background:#fff;border-radius:9px;
    font-size:13px;font-weight:700;color:#2a3746;cursor:pointer;transition:all .15s;display:inline-flex;align-items:center;justify-content:center;}
  .inv-pg:hover:not(:disabled){border-color:var(--navy);color:var(--navy);}
  .inv-pg.active{background:var(--navy);color:#fff;border-color:var(--navy);box-shadow:0 4px 10px -3px rgba(31,58,95,.5);}
  .inv-pg:disabled{opacity:.4;cursor:not-allowed;}
  .inv-pg.dots{border:none;background:none;cursor:default;pointer-events:none;min-width:20px;}
  .page.show{display:block;}

  /* ===== Page header seragam (breadcrumb → judul → aksi) ===== */
  .page-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:20px;flex-wrap:wrap;}
  .page-head .ph-crumb{font-size:12px;color:var(--mut);font-weight:500;margin-bottom:5px;}
  .page-head .ph-crumb b{color:var(--navy);font-weight:600;}
  /* Judul halaman: cukup tegas untuk jadi penanda, tanpa setebal papan iklan.
     600 masih terbaca sebagai judul; 800 membuatnya berat dan menekan isi
     halaman di bawahnya. Jarak hurufnya dinormalkan supaya tidak berdesakan. */
  .page-head .ph-title{font-size:22px;font-weight:700;color:var(--navy);letter-spacing:-.1px;line-height:1.25;}
  .page-head .ph-actions{display:flex;align-items:center;gap:10px;}
  .ph-year{display:flex;align-items:center;gap:8px;background:#fff;border:1px solid var(--line);border-radius:12px;padding:8px 14px;font-size:13px;color:var(--ink);box-shadow:var(--shadow);}
  .ph-year b{color:var(--navy);font-weight:700;}
  .ph-avatar{width:42px;height:42px;border-radius:50%;background:var(--gold);color:var(--navy);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;box-shadow:var(--shadow);flex:0 0 42px;}

  /* Dashboard */
  .dash-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px;}
  .dash-card{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:22px 24px;transition:box-shadow .18s,transform .18s;}
  /* Profil Saya (full-width, satu kartu seperti Manajemen Users) */
  .profil-top{display:flex;align-items:center;gap:16px;}
  .profil-av{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,var(--navy),#24507a);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:23px;flex:0 0 64px;box-shadow:0 4px 14px rgba(21,49,74,.25);}
  .profil-id-nama{font-weight:800;color:var(--navy);font-size:18px;line-height:1.25;}
  .profil-id-role{display:inline-block;margin-top:6px;font-size:12px;font-weight:600;color:var(--gold);background:#fbf3e2;padding:4px 12px;border-radius:20px;}
  .profil-sec-title{font-size:14px;font-weight:700;color:var(--navy);margin-bottom:14px;}
  .profil-hint{font-size:11.5px;color:var(--mut);margin-top:6px;}
  .profil-divider{border-top:1px solid var(--line);margin:22px 0;}
  .profil-form{display:grid;grid-template-columns:1fr;gap:16px;max-width:520px;}
  .profil-form-3{grid-template-columns:repeat(3,1fr);max-width:none;}
  .profil-actions{margin-top:24px;display:flex;justify-content:flex-end;}
  @media(max-width:840px){
    /* Toggle metrik Dashboard PD: bisa geser horizontal di HP agar tidak nabrak ke kanan */
    #pd-metrik-seg{display:flex;max-width:100%;width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;flex-wrap:nowrap;}
    #pd-metrik-seg .an-seg-btn{flex:0 0 auto;white-space:nowrap;}
    .profil-form-3{grid-template-columns:1fr;}
    .profil-actions{justify-content:stretch;}
    .profil-actions .btn{width:100%;}
  }
  .dash-card:hover{box-shadow:var(--shadow-hover);}
  .dash-card h3{margin:0 0 4px;font-size:16px;color:var(--navy);font-weight:700;letter-spacing:-.05px;}
  .dash-card .sub{font-size:12px;color:var(--mut);margin-bottom:12px;}
  .donut-wrap{position:relative;height:210px;}
  .donut-center{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;pointer-events:none;max-width:78%;}
  .donut-center .big{font-size:30px;font-weight:800;color:var(--navy);letter-spacing:-.5px;}
  .donut-center .lbl{font-size:11px;color:var(--mut);}
  .dash-legend{display:flex;justify-content:center;gap:16px;margin-top:8px;flex-wrap:wrap;}
  .dash-legend .li{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--ink);}
  .dash-legend .dot{width:11px;height:11px;border-radius:3px;}

  /* KPI cards */
  .kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:18px 0;}
  .kpi{position:relative;background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:18px 20px;transition:box-shadow .18s,transform .18s;overflow:hidden;}
  .kpi:hover{box-shadow:var(--shadow-hover);transform:translateY(-2px);}
  .kpi::before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:var(--kc,var(--navy));}
  .kpi .kpi-top{display:flex;align-items:center;gap:10px;margin-bottom:12px;}
  .kpi .kpi-ic{width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;background:var(--kbg,#eef2f7);color:var(--kc,var(--navy));flex:0 0 38px;}
  .kpi .kpi-ic svg{width:19px;height:19px;stroke:currentColor;fill:none;stroke-width:2;}
  .kpi .kpi-lbl{font-size:11px;color:var(--mut);font-weight:600;text-transform:uppercase;letter-spacing:.4px;}
  .kpi .kpi-val{font-size:21px;font-weight:700;color:var(--navy);letter-spacing:-.3px;line-height:1.15;white-space:nowrap;}
  .kpi .kpi-note{font-size:11.5px;color:var(--kc,var(--mut));font-weight:600;margin-top:3px;}

  .tbl-tools{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;}
  .tbl-tools input{flex:1;min-width:160px;}
  table.realisasi{width:100%;border-collapse:collapse;font-size:12.5px;}
  /* Tabel NPD: font 115% (~14.4px). Kolom Nominal dijaga 1 baris (nowrap + padding rapat). */
  .npd-table{font-size:14.4px;}
  .npd-scroll{overflow-x:visible;}
  @media(max-width:900px){.npd-scroll{overflow-x:auto;}}
  .npd-table td, .npd-table th{padding:11px 10px;}
  .npd-table td:has(.stat-cell){padding-left:6px;padding-right:6px;}
  /* Kolom Aksi: pastikan 3 ikon per baris muat tanpa scroll horizontal */
  .npd-table td:last-child, .npd-table th:last-child{padding-left:2px;padding-right:2px;}
  .npd-table td:last-child .aksi-wrap{grid-template-columns:repeat(3,28px);gap:3px;width:90px;margin:0 auto;}
  .npd-table td:last-child .aksi-wrap .ic-btn{width:28px;height:28px;}
  .npd-table td:last-child .aksi-wrap .ic-btn svg{width:15px;height:15px;}
  /* Kode Rekening & Tagging: izinkan pecah baris agar kolom sempit tetap terbaca */
  #tbl-daftar-npd td:nth-child(2), #tbl-daftar-npd td:nth-child(3),
  #prst-table td:nth-child(2), #prst-table td:nth-child(3),
  #vrf-table td:nth-child(2), #vrf-table td:nth-child(3){overflow-wrap:anywhere;word-break:break-word;}
  .npd-table td.num{white-space:nowrap;font-variant-numeric:tabular-nums;padding-left:6px;padding-right:8px;}
  .npd-table th.num{white-space:nowrap;}
  .npd-table th.num, #tbl-daftar-npd th.num, #prst-table th.num, #vrf-table th.num{text-align:left;}
  #tbl-daftar-npd td.num, #prst-table td.num, #vrf-table td.num{text-align:left;}
  .npd-table th.st, #tbl-daftar-npd th.st, #prst-table th.st, #vrf-table th.st{text-align:center;}
  /* Judul kolom Nominal ikut rata kanan agar sejajar angkanya - aturan
     dasar hanya mengatur td.num, sehingga judulnya tertinggal rata kiri. */
  .npd-table th.num, #tbl-daftar-npd th.num, #prst-table th.num, #vrf-table th.num{text-align:right;}
  table.realisasi th,table.realisasi td{padding:11px 12px;border-bottom:1px solid var(--line);text-align:left;}
  table.realisasi th{background:#f7f9fc;color:var(--mut);font-weight:700;cursor:pointer;white-space:nowrap;user-select:none;position:sticky;top:0;z-index:2;font-size:10.5px;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--line);}
  table.realisasi th.sortable:hover{background:#eef3f8;color:var(--navy);}
  table.realisasi tbody tr{transition:background .12s;}
  table.realisasi tbody tr:nth-child(even){background:#fafcfe;}
  table.realisasi tbody tr:hover{background:#f1f6fb;}
  table.realisasi td.num{text-align:right;white-space:nowrap;}
  #pd-rekap-table th.num{text-align:right;}
  #pd-rekap-table th.pd-sort:hover{background:#eef2f6;}
  #pd-rekap-table .pd-sarrow{color:var(--gold);font-size:11px;}
  /* Accordion rekap PD */
  .pd-caret{display:inline-block;width:14px;color:var(--mut);font-size:10px;transition:color .15s;margin-right:2px;}
  .pd-bidang-row:hover{background:#f4f8fc;}
  .pd-bidang-row.pd-open{background:#eef3f8;}
  .pd-bidang-row.pd-open .pd-caret{color:var(--navy);}
  .pd-anggota-row{background:#fafbfd;font-size:12.5px;}
  .pd-anggota-row td{padding-top:8px;padding-bottom:8px;border-bottom:1px solid #eef1f5;}
  .pd-anggota-nama{padding-left:28px !important;color:var(--ink);}
  .pd-anggota-jab{display:block;font-size:10.5px;color:var(--mut);font-weight:400;margin-top:1px;}
  table.realisasi .bar{height:6px;border-radius:3px;background:#eef2f7;overflow:hidden;margin-top:3px;}
  table.realisasi .bar i{display:block;height:100%;background:var(--navy);border-radius:3px;}
  /* Chip kode rekening + mini progress sel */
  .kode-chip{display:inline-block;background:#eef2f7;color:#475569;font-size:11px;font-weight:600;padding:3px 9px;border-radius:7px;font-variant-numeric:tabular-nums;}
  .cellbar{display:flex;align-items:center;gap:8px;justify-content:flex-end;}
  .cellbar .ct{width:64px;height:6px;background:#eef2f7;border-radius:50px;overflow:hidden;flex:0 0 64px;}
  .cellbar .cf{height:100%;border-radius:50px;}
  .cellbar b{font-size:12px;font-variant-numeric:tabular-nums;}
  .pill{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;}
  /* Segmented toggle (Analisis: mode garis) */
  .an-seg{display:inline-flex;background:#eef2f7;border-radius:10px;padding:3px;gap:2px;}
  /* Dipakai sebagai <button> maupun <a>; text-decoration dimatikan supaya
     versi tautannya tidak bergaris bawah seperti pranala biasa. */
  .an-seg-btn{border:none;background:transparent;color:var(--mut);font-size:12.5px;font-weight:600;padding:7px 14px;border-radius:8px;cursor:pointer;transition:.15s;text-decoration:none;}
  .an-seg-btn:hover,.an-seg-btn:focus{color:var(--navy);text-decoration:none;}
  .an-seg-btn.active{background:#fff;color:var(--navy);box-shadow:0 1px 3px rgba(15,23,42,.12);}
  /* ===== Pivot tree: Program > Sub Kegiatan > Kode Rekening > Tagging =====
     SALINAN PERSIS aturan table.pivot di GAS (index.html), termasuk
     kekhususannya. Jangan dinaikkan kekhususannya: di GAS aturan tingkat ini
     memang KALAH dari `table.realisasi tbody tr:nth-child(even)` pada tabel
     dasar, sehingga baris genap berlatar #fafcfe dan muncul pola selang-seling
     di atas gradasi tingkat. Itu rupa asli GAS - bukan cacat. Menambah
     `table.realisasi` atau `tbody tr` pada selektor di bawah akan
     menghilangkan pola tersebut. */
  table.pivot td{vertical-align:middle;}
  table.pivot .row-lvl0{background:var(--navy-l);font-weight:700;cursor:pointer;}
  table.pivot .row-lvl0:hover{background:#dde6ef;}
  table.pivot .row-lvl1{background:#eef4fa;font-weight:600;cursor:pointer;}
  table.pivot .row-lvl1:hover{background:#e4edf6;}
  table.pivot .row-lvl2{background:#f8fafc;cursor:pointer;}
  table.pivot .row-lvl2:hover{background:#eef3f8;}
  table.pivot .row-lvl3{background:#fff;}
  table.pivot .uraian{display:flex;align-items:center;gap:7px;}
  table.pivot .tgl{width:14px;height:14px;flex:0 0 14px;transition:transform .15s;color:var(--navy);}
  table.pivot .tgl.open{transform:rotate(90deg);}
  table.pivot .ind1{padding-left:22px;}
  table.pivot .ind2{padding-left:46px;}
  table.pivot .ind3{padding-left:70px;color:var(--mut);}
  table.pivot .spacer{width:14px;flex:0 0 14px;display:inline-block;}
  /* Selang-seling baris Program - TIDAK ada di GAS, hanya dipakai Simulasi
     Anggaran. Kekhususannya (0,3,1) sudah melebihi nth-child(even). */
  table.pivot .row-lvl0.lvl0-gelap{background:#dbe5ee;}
  table.pivot .row-lvl0.lvl0-gelap:hover{background:#cddce8;}
  table.pivot .row-lvl0.lvl0-terang{background:#f3f6fa;}
  table.pivot .row-lvl0.lvl0-terang:hover{background:#e7edf3;}
  @media(max-width:840px){
    .sidebar{
      display:flex;
      position:fixed;top:0;left:0;height:100vh;width:270px;z-index:60;
      transform:translateX(-100%);transition:transform .25s ease;
      border-radius:0;margin:0;
    }
    .sidebar.open{transform:translateX(0);}
    .shell{display:block;}
    .topbar{left:0;}
    .topbar .burger{display:flex;}
    .main{padding-left:0;padding-right:0;}
    .dash-grid{grid-template-columns:1fr;}
    .kpi-grid{grid-template-columns:1fr 1fr;gap:12px;}
    .kpi{padding:14px 15px;}
    .kpi .kpi-ic{width:32px;height:32px;flex:0 0 32px;border-radius:9px;}
    .kpi .kpi-ic svg{width:16px;height:16px;}
    .kpi .kpi-top{gap:8px;margin-bottom:10px;}
    .kpi .kpi-lbl{font-size:10px;letter-spacing:.3px;}
    .kpi .kpi-val{font-size:16.5px;}
    .kpi .kpi-note{font-size:10.5px;}
    .page-head{margin-bottom:16px;}
    .page-head .ph-title{font-size:19px;}
    /* Aksi: di HP jadi 2 kolom (2x3), ikon lebih besar agar mudah disentuh */
    .aksi-wrap{grid-template-columns:repeat(2,36px);gap:6px;width:78px;}
    .aksi-wrap .ic-btn{width:36px;height:36px;}
    .aksi-wrap .ic-btn svg{width:18px;height:18px;}
    /* Tabel NPD di HP: beri lebar minimum agar kolom layak, wrapper scroll ke kanan (pola tabel SP) */
    .npd-table{min-width:940px!important;width:auto!important;table-layout:auto!important;}
    .npd-table th, .npd-table td{white-space:normal;word-break:normal;overflow-wrap:break-word;}
    .npd-table td.num{white-space:nowrap;}
    /* Aksi di HP tetap 3x2 dalam kolomnya (tabel sudah lebar, jadi muat) */
    .npd-table .aksi-wrap{grid-template-columns:repeat(3,30px);width:102px;}
    .npd-table .aksi-wrap .ic-btn{width:30px;height:30px;}
    .npd-table .aksi-wrap .ic-btn svg{width:16px;height:16px;}
    .sb-overlay{
      display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:55;
    }
    .sb-overlay.show{display:block;}
    .sb-close{display:flex;}
    .sb-collapse{display:none;}
    .sb-expand{display:none!important;}
  }
  .sb-close{display:none;align-items:center;justify-content:center;
    width:30px;height:30px;border-radius:7px;cursor:pointer;margin-left:auto;flex:0 0 30px;}
  .sb-close svg{width:18px;height:18px;stroke:#c4d4e6;stroke-width:2.2;fill:none;}
  .sb-close:hover{background:rgba(255,255,255,.1);}

  .hd{background:var(--navy);padding:18px 22px;display:flex;align-items:center;gap:13px;}
  .hd .ic{width:64px;height:64px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex:0 0 64px;}
  .hd .t1{color:#fff;font-size:16px;font-weight:600;}
  .hd .t2{color:#9db8d6;font-size:12.5px;margin-top:1px;}

  .steps{display:flex;gap:6px;padding:15px 22px 2px;flex-wrap:wrap;}
  .step{flex:1;min-width:118px;display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:var(--radius-sm);border:1px solid transparent;}
  .step .n{width:22px;height:22px;border-radius:50%;background:var(--line);color:var(--mut);
           display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;flex:0 0 22px;}
  .step .lb{font-size:12.5px;color:var(--mut);}
  .step.active{background:var(--navy-l);border-color:var(--navy);}
  .step.active .n{background:var(--navy);color:#fff;}
  .step.active .lb{color:var(--navy);font-weight:600;}
  .step.done .n{background:var(--ok);color:#fff;}

  .body{padding:10px 22px 22px;}
  .pane{display:none;} .pane.show{display:block;}
  .kd-mode-card{display:block;width:100%;text-align:left;background:#fff;border:1.5px solid var(--line);border-radius:12px;padding:14px 16px;cursor:pointer;transition:border-color .15s,box-shadow .15s;}
  .kd-mode-card:hover{border-color:var(--navy);box-shadow:0 2px 10px rgba(31,58,95,.12);}
  /* Mode kontribusi: sembunyikan section perjalanan. Mode perjalanan: tampilkan. */
  .kd-sec-perjalanan{display:none;}
  body.kd-perjalanan .kd-sec-perjalanan{display:block;}
  /* Mode perjalanan: kontribusi terkunci (read-only) */
  body.kd-perjalanan .kd-sec-kontribusi{opacity:.72;}
  input.locked{background:#f1f3f5 !important;cursor:not-allowed;}

  label.fl{font-size:12.5px;color:var(--navy);font-weight:600;display:block;margin:14px 0 5px;}
  label.fl .ti{font-size:15px;vertical-align:-2px;margin-right:5px;}
  select,input,textarea{
    width:100%;padding:9px 11px;border:1px solid var(--line);border-radius:var(--radius-sm);
    font-size:13.5px;font-family:inherit;color:var(--ink);background:#fff;outline:none;transition:border .15s,box-shadow .15s;
    -webkit-appearance:none;appearance:none;
  }
  select{background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%231e3a5f' stroke-width='2'><polyline points='6 9 12 15 18 9'/></svg>");
         background-repeat:no-repeat;background-position:right 11px center;padding-right:34px;cursor:pointer;}
  select:focus,input:focus,textarea:focus{border-color:var(--navy);box-shadow:0 0 0 3px rgba(30,58,95,.12);}
  select:disabled{background-color:#f8fafc;color:#94a3b8;cursor:not-allowed;}
  textarea{resize:vertical;min-height:64px;}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
  @media(max-width:560px){.row{grid-template-columns:1fr;}}

  .metrics{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px;}
  .metric{border-radius:var(--radius-sm);padding:12px 14px;}
  .metric .ml{font-size:12px;margin-bottom:3px;} .metric .mv{font-size:18px;font-weight:700;}
  .m-pagu{background:var(--navy-l);} .m-pagu .ml{color:var(--mut);} .m-pagu .mv{color:var(--navy);}
  .m-sisa{background:var(--ok-bg);} .m-sisa .ml{color:var(--ok);} .m-sisa .mv{color:var(--ok);}

  .seg{display:flex;gap:8px;margin-top:5px;}
  .seg label{flex:1;border:1px solid var(--line);border-radius:var(--radius-sm);padding:10px;text-align:center;cursor:pointer;font-size:13.5px;transition:.15s;}
  .seg input{display:none;}
  .seg input:checked + span{color:var(--navy);font-weight:600;}
  .seg label:has(input:checked){border-color:var(--navy);background:var(--navy-l);}

  .auto{background:#f8fafc;border:1px dashed var(--line);border-radius:var(--radius-sm);padding:11px 13px;margin-top:14px;font-size:13px;}
  .auto .ai{display:flex;justify-content:space-between;padding:3px 0;}
  .auto .ai .k{color:var(--mut);} .auto .ai .v{font-weight:600;text-align:right;}

  .pen{border:1px solid var(--line);border-radius:var(--radius-sm);padding:13px;margin-top:12px;position:relative;}
  .pen .del{position:absolute;top:9px;right:9px;background:var(--err-bg);color:var(--err);border:none;border-radius:7px;width:26px;height:26px;cursor:pointer;font-size:15px;}
  .pen h4{margin:0 0 4px;font-size:13px;color:var(--navy);}
  .add{margin-top:12px;width:100%;border:1px dashed var(--navy);background:var(--navy-l);color:var(--navy);
       padding:11px;border-radius:var(--radius-sm);font-size:13.5px;font-weight:600;cursor:pointer;}

  .sumbar{display:flex;justify-content:space-between;align-items:center;margin-top:14px;padding:12px 14px;border-radius:var(--radius-sm);font-size:13.5px;}
  .sumbar.ok{background:var(--ok-bg);color:var(--ok);} .sumbar.bad{background:var(--err-bg);color:var(--err);}
  .sumbar .v{font-weight:700;}

  .nav{display:flex;justify-content:space-between;gap:10px;margin-top:22px;}
  .btn{display:inline-block;padding:10px 18px;border-radius:var(--radius-sm);font-size:13.5px;font-weight:600;cursor:pointer;border:1px solid var(--line);background:#fff;color:var(--ink);transition:.15s;text-decoration:none;}
  .btn:hover{background:#f8fafc;}
  .btn.prim{background:var(--navy);color:#fff;border-color:var(--navy);}
  .btn.prim:hover{background:var(--navy-d);}
  .btn.danger{background:#dc2626;color:#fff;border-color:#dc2626;}
  .btn.danger:hover{background:#b91c1c;border-color:#b91c1c;}
  .btn:disabled{opacity:.45;cursor:not-allowed;}

  .rev{font-size:13.5px;}
  .rev .grp{border:1px solid var(--line);border-radius:var(--radius-sm);padding:13px 15px;margin-top:12px;}
  .rev .grp .gt{font-size:12px;color:var(--navy);font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin-bottom:7px;}
  .rev .li{display:flex;justify-content:space-between;padding:3px 0;border-bottom:1px dashed #eef2f7;}
  .rev .li:last-child{border-bottom:none;} .rev .li .k{color:var(--mut);} .rev .li .v{font-weight:600;text-align:right;max-width:60%;}

  .toast{position:fixed;left:50%;bottom:24px;transform:translateX(-50%);background:var(--navy);color:#fff;padding:12px 18px;border-radius:10px;font-size:13.5px;box-shadow:0 4px 14px rgba(0,0,0,.2);display:none;z-index:50;max-width:90%;}
  .spin{display:inline-block;width:15px;height:15px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:sp .7s linear infinite;vertical-align:-2px;margin-right:6px;}
  @keyframes sp{to{transform:rotate(360deg);}}

  .result{text-align:center;padding:8px 0;}
  .result .ok-ic{display:flex;justify-content:center;margin-bottom:4px;}
  .result .ok-ic svg{width:56px;height:56px;}
  .result .rn{font-size:18px;font-weight:700;color:var(--navy);margin:6px 0 2px;}
  .result a.dl{display:inline-flex;align-items:center;gap:7px;margin:8px 6px 0;padding:11px 18px;background:var(--navy);color:#fff;border-radius:var(--radius-sm);text-decoration:none;font-size:13.5px;font-weight:600;}
  .err-box{background:var(--err-bg);color:var(--err);border-radius:var(--radius-sm);padding:11px 13px;margin-top:12px;font-size:13px;display:none;}

  /* Pemilih jenis NPD */
  .pick{display:grid;grid-auto-flow:column;grid-auto-columns:1fr;gap:14px;margin:18px 0;}
  @media(max-width:720px){.pick{grid-auto-flow:row;grid-template-columns:1fr 1fr;grid-auto-columns:auto;}}
  .pick .opt{border:1.5px solid var(--line);border-radius:var(--radius);padding:20px 14px;cursor:pointer;text-align:center;transition:.15s;background:#fff;text-decoration:none;}
  .pick .opt:hover{border-color:var(--navy);background:var(--navy-l);}
  .pick .opt .ic{width:64px;height:64px;margin:0 auto 12px;background:var(--navy);border-radius:50%;display:flex;align-items:center;justify-content:center;}
  .pick .opt .ic svg{width:32px;height:32px;stroke:#fff;fill:none;stroke-width:1.8;}
  .pick .opt:hover .ic{background:var(--navy-d);}
  .pick .opt .t{font-size:15px;font-weight:600;color:var(--navy);}
  .pick .opt .d{font-size:12px;color:var(--mut);margin-top:4px;}
  /* Form Tambah Rekanan */
  .rk-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 16px;}
  .rk-field{display:flex;flex-direction:column;min-width:0;}
  .rk-field.rk-col2{grid-column:1 / -1;}
  .rk-field input, .rk-field select{width:100%;box-sizing:border-box;}
  @media(max-width:640px){ .rk-grid{grid-template-columns:1fr;} .rk-field.rk-col2{grid-column:auto;} }

  /* Anggota tim perjalanan */
  .anggota{border:1px solid #c8d6e5;border-left:4px solid var(--navy);border-radius:12px;padding:15px;margin-top:20px;position:relative;background:#fff;box-shadow:0 5px 16px rgba(30,58,95,.07);}
  .anggota .del{position:absolute;top:15px;right:15px;background:var(--err-bg);color:var(--err);border:none;border-radius:8px;width:30px;height:30px;cursor:pointer;font-size:17px;}
  .anggota h4{margin:0 0 8px;font-size:13px;color:var(--navy);}
  .anggota .sub{font-size:11.5px;color:var(--mut);font-weight:600;margin:10px 0 2px;text-transform:uppercase;letter-spacing:.3px;}
  .tim-member-head{display:flex;align-items:center;gap:11px;min-height:42px;padding:0 42px 12px 0;margin-bottom:13px;border-bottom:1px solid #e2e8f0;}
  .tim-member-number{display:grid;place-items:center;flex:0 0 40px;width:40px;height:40px;border-radius:11px;background:linear-gradient(145deg,var(--navy),#315f91);color:#fff;font-size:18px;font-weight:900;box-shadow:0 4px 10px rgba(30,58,95,.22);}
  .tim-member-head>div{display:flex;min-width:0;flex-direction:column;gap:2px;}
  .tim-member-eyebrow{color:var(--mut);font-size:10px;font-weight:800;letter-spacing:.7px;text-transform:uppercase;}
  .tim-member-head strong{overflow:hidden;color:var(--navy);font-size:16px;line-height:1.25;text-overflow:ellipsis;white-space:nowrap;}
  .tim-copy{display:grid;grid-template-columns:34px minmax(0,1fr) auto;align-items:center;gap:10px;margin:10px 0 14px;padding:11px 12px;border:1px solid #c7d9ee;border-radius:10px;background:linear-gradient(135deg,#f7fbff 0%,#eef6ff 100%);}
  .tim-copy[hidden]{display:none;}
  .tim-copy-icon{display:grid;place-items:center;width:34px;height:34px;border-radius:9px;background:#dbeafe;color:#1d4ed8;font-size:19px;font-weight:800;}
  .tim-copy-field{min-width:0;}
  .tim-copy-field .fl{margin-bottom:4px;color:#1e3a5f;}
  .tim-copy-field select{height:36px;padding-top:6px;padding-bottom:6px;}
  .tim-copy-field .mini{display:block;margin-top:4px;}
  .tim-copy-btn{white-space:nowrap;padding:9px 12px;}
  .sp-anggota-card{margin-top:20px;padding:16px;border:1px solid var(--line);border-radius:12px;background:linear-gradient(145deg,#fff 0%,#f8fbff 100%);}
  .sp-anggota-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:12px;}
  .sp-anggota-judul{display:flex;align-items:center;gap:9px;min-width:0;}
  .sp-anggota-head h3{margin:0;color:var(--navy);font-size:16px;line-height:1.3;}
  /* Pil jumlah anggota: umpan balik langsung tiap kartu ditambah/dihapus. */
  .sp-anggota-hitung{flex:0 0 auto;padding:3px 10px;border-radius:999px;background:var(--navy-l);color:var(--navy);font-size:11.5px;font-weight:700;letter-spacing:.2px;}
  .sp-anggota-hitung[hidden]{display:none;}
  .sp-anggota-btn{display:inline-flex;align-items:center;gap:7px;flex:0 0 auto;white-space:nowrap;}
  .sp-anggota-btn svg{width:15px;height:15px;stroke:currentColor;stroke-width:2.4;fill:none;stroke-linecap:round;}
  /* Kartu anggota SP: baris judul (nomor + aksi) di atas, isian di bawahnya
     dalam grid 3 kolom yang membungkus ke baris berikutnya. Nomor sejajar
     judul, bukan menggantung di atas kolom isian. */
  .sp-anggota-row{position:relative;padding:12px 13px 13px;margin-top:9px;border:1px solid #dce5ef;border-radius:11px;background:#fff;box-shadow:0 1px 2px rgba(15,42,71,.04);transition:border-color .15s,box-shadow .15s;}
  .sp-anggota-row:hover{border-color:#c3d3e4;box-shadow:0 3px 10px rgba(15,42,71,.07);}
  .sp-anggota-row:focus-within{border-color:var(--navy);box-shadow:0 3px 12px rgba(15,42,71,.10);}
  .sp-anggota-row[data-manual="1"]{border-color:#b8c6d8;background:#fbfdff;}
  .sp-anggota-bar{display:flex;align-items:center;gap:9px;margin-bottom:11px;padding-bottom:9px;border-bottom:1px dashed #e2e8f0;}
  /* Nomor bulat, mirip avatar - menandai tiap kartu sebagai satu orang. */
  .sp-anggota-no{display:grid;place-items:center;width:27px;height:27px;flex:0 0 27px;border-radius:50%;background:var(--navy);color:#fff;font-weight:700;font-size:12px;}
  .sp-anggota-title{font-size:12.5px;font-weight:700;color:var(--navy);margin-right:auto;}
  .sp-manual-chip{padding:5px 11px 5px 9px;font-size:12px;gap:7px;border-radius:8px;}
  .sp-manual-chip .komp-box{width:16px;height:16px;flex:0 0 16px;border-radius:4px;}
  .sp-manual-chip .komp-box svg{width:10px;height:10px;}
  .sp-anggota-fields{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;min-width:0;}
  .sp-anggota-fields .sp-f-nama{grid-column:span 2;}
  .sp-anggota-row .fg{margin:0;min-width:0;}
  .sp-anggota-row input[readonly]{background:#f1f5f9;color:#475569;}
  .sp-anggota-row .ic-btn{font-size:20px;flex:0 0 auto;}
  /* Catatan mode manual hanya muncul saat modenya aktif. */
  .sp-anggota-note{display:none;margin-top:9px;padding:7px 9px;border-radius:7px;background:#f1f5f9;color:#64748b;font-size:11.5px;line-height:1.45;}
  .sp-anggota-row[data-manual="1"] .sp-anggota-note{display:block;}
  /* Empty state: ikon + judul + petunjuk singkat, bukan sekadar sebaris teks. */
  .sp-anggota-empty{display:flex;flex-direction:column;align-items:center;gap:3px;padding:26px 18px;text-align:center;border:1.5px dashed #ccd8e5;border-radius:11px;background:#fbfdff;color:var(--mut);}
  .sp-anggota-empty svg{width:30px;height:30px;stroke:#9db3c8;stroke-width:1.7;fill:none;stroke-linecap:round;stroke-linejoin:round;margin-bottom:5px;}
  .sp-anggota-empty-judul{font-size:13px;font-weight:700;color:#475569;}
  .sp-anggota-empty-sub{font-size:11.5px;line-height:1.5;max-width:44ch;}
  .sp-anggota-empty[hidden]{display:none;}
  .sp-anggota-card[hidden]{display:none;}

  /* Tombol tambah kedua: strip putus-putus selebar kartu, di bawah daftar. */
  .sp-anggota-add-row{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;margin-top:10px;padding:11px 14px;border:1.5px dashed #c3d3e4;border-radius:10px;background:transparent;color:var(--navy);font-size:12.5px;font-weight:700;font-family:inherit;cursor:pointer;transition:border-color .15s,background .15s,color .15s;}
  .sp-anggota-add-row:hover{border-color:var(--navy);background:var(--navy-l);}
  .sp-anggota-add-row:focus-visible{outline:2px solid var(--navy);outline-offset:2px;}
  .sp-anggota-add-row svg{width:15px;height:15px;stroke:currentColor;stroke-width:2.4;fill:none;stroke-linecap:round;}

  /* Komponen Pembayaran: chip checklist dengan kotak centang sendiri
     (bukan checkbox native) supaya status tercentangnya jelas terlihat.
     .komp-txt nowrap: label seperti "Uang Harian" tidak boleh patah dua baris. */
  .komp-pilih{display:flex;gap:10px;flex-wrap:wrap;padding:6px 0 2px;}
  .komp-chip{position:relative;display:flex;align-items:center;gap:8px;padding:9px 16px 9px 12px;border:1.6px solid var(--line);border-radius:10px;cursor:pointer;font-size:13.5px;color:#334155;background:#fff;transition:border-color .15s,background .15s,color .15s;user-select:none;}
  .komp-chip:hover{border-color:#94a3b8;}
  .komp-chip input{position:absolute;opacity:0;width:0;height:0;pointer-events:none;}
  .komp-box{width:19px;height:19px;flex:0 0 19px;border-radius:5px;border:1.8px solid #94a3b8;background:#fff;display:flex;align-items:center;justify-content:center;transition:border-color .15s,background .15s;}
  .komp-box svg{width:12px;height:12px;stroke:#fff;stroke-width:2.6;fill:none;stroke-linecap:round;stroke-linejoin:round;opacity:0;transform:scale(.6);transition:opacity .12s,transform .12s;}
  .komp-chip input:checked ~ .komp-box{background:var(--navy);border-color:var(--navy);}
  .komp-chip input:checked ~ .komp-box svg{opacity:1;transform:scale(1);}
  .komp-chip:has(input:checked){border-color:var(--navy);background:#eef3f8;color:var(--navy);font-weight:600;}
  .komp-chip input:disabled ~ .komp-box{opacity:.55;}
  .komp-chip:has(input:disabled){cursor:not-allowed;opacity:.65;}
  .komp-txt{white-space:nowrap;}

  /* Dua toggle bertumpuk pada kolom Aksi halaman Data SP. */
  /* ===== Penanda SP: Monitoring & Sumber NPD =====
     Di GAS hanya sakelar hijau polos. Di sini tiap penanda dibuat sebagai
     satu kepingan utuh - label, sakelar, dan keadaannya dibaca sekaligus,
     dan warnanya membedakan MAKNA kedua penanda, bukan sekadar hidup/mati:
     biru navy = tampil di Monitoring, emas = dipakai sebagai sumber NPD. */
  .sp-toggles{display:inline-flex;flex-direction:column;gap:6px;vertical-align:middle;}
  .sp-toggle{display:inline-flex;align-items:center;gap:9px;justify-content:space-between;
    padding:5px 9px;border:1px solid var(--line);border-radius:20px;background:#fff;
    transition:border-color .15s,background .15s,box-shadow .15s;}
  .sp-toggle:hover{border-color:#c3d2e1;}
  .sp-toggle-lbl{font-size:11px;color:var(--mut);font-weight:700;white-space:nowrap;
    letter-spacing:.2px;text-align:left;min-width:78px;}
  .sp-toggle.aktif{background:var(--navy-l);border-color:#bed0e2;}
  .sp-toggle.aktif .sp-toggle-lbl{color:var(--navy);}
  .sp-toggle.sumber.aktif{background:#fdf6e6;border-color:#ecd9a8;}
  .sp-toggle.sumber.aktif .sp-toggle-lbl{color:var(--gold-d);}
  .sp-toggle.menunggu{opacity:.6;pointer-events:none;}

  /* Timeline progres SP (Monitoring SP). */
  .sp-tl-row > td{background:#f8fafc;padding:12px 14px;}
  .sp-tl-head{font-weight:700;color:var(--navy);font-size:13px;margin-bottom:10px;}
  .sp-tl-scroll{overflow-x:auto;padding:6px 2px 2px;}
  .sp-tl{display:flex;align-items:flex-start;}
  .sp-tl-node{position:relative;flex:1 0 118px;text-align:center;padding:0 4px;}
  .sp-tl-line{position:absolute;left:50%;right:-50%;top:6px;height:2px;z-index:0;}
  .sp-tl-dot{position:relative;z-index:1;display:inline-block;width:14px;height:14px;border-radius:50%;}
  .sp-tl-lbl{font-weight:600;font-size:11.5px;line-height:1.25;margin-top:7px;}
  .sp-tl-ts{color:#475569;font-size:10.5px;margin-top:2px;}
  .sp-tl-note{color:#64748b;font-size:10.5px;margin-top:2px;font-style:italic;}
  .sp-tl-role{color:var(--navy);font-size:10.5px;margin-top:2px;font-weight:700;font-style:italic;}
  .sp-baris-klik{cursor:pointer;}
  .sp-baris-klik:hover{background:#f8fbff;}
  .row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;}
  @media(max-width:980px){.sp-anggota-fields{grid-template-columns:repeat(2,minmax(0,1fr));}.sp-anggota-fields .sp-f-nama{grid-column:span 2;}}
  @media(max-width:720px){.sp-anggota-head{flex-direction:column}.sp-anggota-head .btn{width:100%;text-align:center;}.sp-anggota-fields{grid-template-columns:1fr;}.sp-anggota-fields .sp-f-nama{grid-column:auto;}.sp-anggota-title{font-size:12px;}}
  @media(max-width:720px){.tim-copy{grid-template-columns:34px minmax(0,1fr)}.tim-copy-btn{grid-column:1/-1;width:100%;}}
  @media(max-width:560px){.row3{grid-template-columns:1fr;}}
  .mini{font-size:12px;color:var(--mut);margin-top:4px;}
  .badge-tot{background:var(--navy-l);border-radius:var(--radius-sm);padding:8px 11px;margin-top:10px;font-size:13px;display:flex;justify-content:space-between;}
  .badge-tot .v{font-weight:700;color:var(--navy);}
</style>
