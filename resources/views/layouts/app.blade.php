<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'i-Finance') &mdash; Inspektorat Jabar</title>
@include('layouts.partials.styles')
</head>
<body>
{{-- Dijalankan sebelum badan halaman digambar supaya tidak ada kedipan
     terang saat pengguna memilih mode gelap. --}}
<script>
  (function(){
    try{
      if(localStorage.getItem('ifinance-sidebar-collapsed')==='1'){document.documentElement.classList.add('sidebar-collapsed');}
      if(localStorage.getItem('ifinance-tema')==='gelap'){document.documentElement.setAttribute('data-tema','gelap');}
    }catch(e){}
  })();
</script>

<div class="sb-overlay" id="sb-overlay"></div>
<div class="shell" id="app-shell">
  <aside class="sidebar">
    <div class="sb-head">
      <div class="ic" id="sb-logo" role="button" tabindex="0" title="Tampilkan menu"><img src="data:image/webp;base64,UklGRhYZAABXRUJQVlA4WAoAAAAQAAAAXwAAaAAAQUxQSDsGAAAB8L/9//lG/v/P6nq7P5K0nTa1bbfbTrfG2FNrXO3Ytrl62rZt27Zdru3H/X774ZGkaZ63iJiA7bvWrmjpq6mp7qlbtWP7WPey9bviu7aXti8dqk5JjSiIKIyLy0qJ8SDMDWeEBbcLIV1bvqLWijq59uSV1WOdg+tHXtV2dGtnd1FtV0d1kteVnpEe5Y3zxgOKiEi547zktohIRShaaEU+Hug4d6pj88jm5auP9/YNN3RcXNZQWdaWWbSiLDupqjPcm5taGaMQBr9hboT6cHv1ytHBFQMtE10bV23obW3qvtPQPlSd11HUXjx2tLy+YXlnaWluRWFGbk5GRkZ6enpGZkFGflZGCGZF+djbvHTv66sOTM49MjkzO/vo3Nz0f6an/jcz/d+pqdn//nNq7qHZ6cnp6anpmenpGb/TM9MzITj18BZYAPrqWg9+uu84S3wHCsChoyuP7Vx62NhG2lfM9wkE9J678LqKjP2sWVrDj9ZCAVfXTXzoYM1OgVjzCCzg2CcmH35N7bhI5gQUMHDqgwffXToiEp+FBVQWjR+9kD0mkc3nHTvOHN4zljkh06uhgOo7xyr25o/LdBMWUFfbf/OLaRMyXXdM7O4bPrtop0SaTzgOrdn57cvRe2U67di5Z9dHN2OfTAcdpyfe0t248aRENt+BAjrf+NQnz3ecZyPRTVhA/wf4k++v2M9aojOOnSc/tvdkwi6JNB9y7Bi5tPtkjVD7HIf6Tr3hudXjEtl8wXF6710HjiYelOmy4/w7WlZ1ZO+RSPO4Y2T4nsV3Ve6U6Zjj6rKeDm/JEYlsvuZYn15cnOjeJ5Hm447F2a1FS8t3yvQmKKChaNmy4rjNbOSx+Q4sIDxzoNaNJYaNQNcdSRuywtJUmxbpfiigYCQ1JhLtMt1xlLbm5cRHttkCaT4DC4graSpd15T/kEhHHGnhWSV1ZZlTIp1y5OXU7cil7EmBDO9wRMaGp6S4w37CWqBORzhAMRHqW/IY1iuhAG+c25MWg/ewLY3mf+eCgMTEiKKkXDwg0a/jHCo9LSyvHAdZS2Pz5ywHFWWioAwDbKTR/C2PIzbBgkpR9S+zEcbmtxMBiI0FJRWojGmBrsICEA24CxPD3D9hLYzm01AAFkWBCIQPsS2LYT3qw7eFK/K83ORLhfnoZyPNC/W+yEIYFCqeZiOK5h8sAjkAhIcB3j+wFsXmzyFQAuHdbAvzIaIAAAsnWQvzJQsUgELK74TRfAkWAiS8jjVLaviFBlIBKOQ/aoworPVBBGJhH2sWhn8VCfKn8E5jC8NGj0L5IdC3WUtj8ydAAcT/TR7Nv4kC+cueYyON4YfLoXwpNLwkDxt9GuTLwmoWSPPrAtnFmsW1+UsWyM9+iTT/NiaAXjbyGJ4rhvJBiPmD0QI9VeMHCg+yLdAjdf5IJX6ctTTa/L4K5AsKOTNspOE3xQYACw+wLYvhJ9qgEADtZC2K4ee3uAiBYK8wmn8dj3l0shHmu4sCI/J+l21ZvhoWGBQ2GjaC2OZDIASucPPftiR8H9Q8iOg+1mJo/lMRaB6wcEIOw//rAmF+B8Qw/KeVHkIQxo0RwuZ3IZgKOVNGS/FmqCCA6ENsy6D5GxGgYCD5Z/yKEcDwEzeigwKF1lk2Wpv/N9vMjngQXIWGTxlm1rbWxhgTesaw0Yb51YsRbAJ6ht9p2K82oWO0MVqzz4deG+GiYEEBUCuOP/CmLzwy98zDzDpEjGafrzzOL3xmqMaLhSRlwektLK6vuG1Yh4Rh5n899djf3nmoZ1NnJGDRQgAgy1IEn9v+wfbCGc2P3+gtX1GVYMFpKagF8klKKWWhfpq1WSDDPHcI/i1FCF+P0FUofQezXghj88yJQpdSpBQRAJD3xEPdHgoVENxXH2Y7eJr5Xy0InND5JP+1FhQqUEDbX1jrIGl+7q/XYamAQDT8lzoihC4p5HyR2TZBMDb//nCpRxHm66pDiCtEjf6D2TbzMJr5v6sQZAoxKKD23TPMtgmI+Zn33O0homAQQp4UUP0Rm1n7Mdp+9M3VbhDEJAW19j1PsdaGjdbMv29UAEFSBeCeP7DvX51rBhSEJYuQeusvNj/ztd9tBYggc8ryB45446AIMhOABAUQ5CYLIEIIAwBWUDggtBIAANA/AJ0BKmAAaQA+ORaIQyIhIRpMdzAgA4SxAGDkgB+AHXDfZ8z+IHsbcn9rL0nQnFN7iM7/+W9RnmAc5PzHfsh6u3+z/bn3O/4X1AP57/kOsq/t//D9hz+P/7D05/ZS/uv/T/cL2g7wT+5eC/hB8aeyXrHZQ+nD+R9D/5B9p/t/9y/aH80vkTvp+AP8B+XvwC/jH8m/tP9c/cr+5/tp7jPtm8TPS/79/vvUF9gPnX+N/vH7rf370Z/4D0I+tH9w/Mj+yfYB/Kv5j/g/7L+2f9+///1X/hPCg+zf6n/Ve4B/Jv6L/mf7X+1P+i///2wfw3/F/w/5ce1P89/tn/E/xH+N+Qj+T/0X/Zf3n/I/+P/Mf//6s/Xr+yH/t90z9eGLlr++MMCu/UJATLt59ElABTqFO5vawOnJSxJ/XUlp6E5YNjomI+kyf/R6THk2+Cjn5lIWLGx3kZDtRUBVMMh+r7RoRRGq+YFAhb31keXCSrT3CDXATYt9vkC9G/TsvQ2UWST+1nCerI0P9NbCvt+Mh/KVQqK/WjchTrMdZ0Tds2CmG5EC28FTuUxytFzCVRRjEiVlsfabPnFIfChcgzuPPLp1KVnZHjDzbSHMYZpRlbDa7vCgsy1cnHd51MgwS0LNzond8zcX9OHrx8ExQHvzVaCdhcNQOh+1/+ivNu2Qmn3fN87wRgZ+EAD+/EXHxDdv/N9O/BX/oChlBnnco+/Wyl8lcbizlVo1aUQ7U55P4JJ5hKkS/MUhOGoM1wSGpG6HeWEJfLpjoPKNvIAKyuWotjLubYS1+fie8f8sQNkBbNNTJEOldRUSL2BG+2uAroa99aECD7nC4CNZuPh393j83qLcCXpWVWUUvBZRzQw8enQHinBF7Fp4m4Byr7ZuxCL9NliKgHy7FhbO90HfBWqXE6tiTuQMjgSh/I0NaXEdQW3N2tOwr4qL225+hP2B+Wx0N1CeD0PoZSwL1R6KePKcIBhOSPVYwL6PBvVIEJo6pSo32riucMPwFVDF08iwIY6o/TgKh94bzdDhx/q87Nw3KjfPw+7PW8SoCDilO+VC6MueLGPeiRfuBydDfT6VjoG90iZ/un2aKcY94H+ESjP/YRTrkExmeNZnioIODJ+88T1w7SrxB7il8Cg8chTk9EKbofMZOH6i80OEOGLVXdNN/mIykOPZxBb8ALluMsRaT2PHXCetfyQHR4pvvLIVOrWs7OSO02qd/Eoov18Y8RURLN9iS7NXfm5763yqjUP1uk8QwSFmfyhXjCmbCh1/iQcLIuV4sP0j7brbPklAJleT+/JWnpL/DX7qgGNrgV6RhTg8EPHjBZNq8BGJF46DgHXFNL5ZIc7pbIPqXeOdterVPsf9vjH+HTzVCoXg18WklkH4t6bswY03tD7PZWLxZZ4CayHFWv91UCqsNxGP2EEUYYiO6mHJAFKiRF1qH8HnhvlVzWycPC+oCkdFcdfGAwuSwtrFAlxfc+fIn9HvNsJDFnJ2eyCU766PyNV8//8LFWIErR9VZ1bZiLpykoHDgDL65jzpHWT0ycuM/BNFsb5q5ElwL5ZOyOxNibqJe+9ztZBf7/k2/BbOa/nQniMr4IbMNhdd0XcdJPBkEDE4jh7hqeRVpWF7xIxf1lGAvSYzRCQQiKtDnT4I0A/eaB/esk1A+fvVZnU+ZEG1Hyrpw3zN7y7nKbudn5ii2xUbI7GTAsv8gG9ETpKGSpFfDdhMYnZy/CIa2FD9qjSoJsaWRMuwYLi6pCRsVd+w257U+D3s6rVqsl0qoOYKDgAChm+u4mh2Sv5/8iHO+23i+VB6rAW59v5T146MKDHO2JUNBxutn4iZZK80w3+Q0lxOXpj35/E+HYvPn1Jcn3plHRyUGPIONtYdln/A8WSIxh/kYcdX0CEJS4d9M3bGPqQJ/9veZ1Q4KKB/j91JpY/PgXcoFAfxBApjpYnccggDyYaLguUxYrDTRZa4+xpFk1K+lv4LaUbHqfSX8oiCzA/Fxz+PjzuLUNFGIOvSnU/qlTgFaD/5MNlW5NDojkMISMdEs1KxJtK0yZ4fgkff/9tIRc3th7O9AFLaA3rxfch9WZOQAHrhqDORroabtGop3j+JoNIdxq+BR3eZg+BHlktP4vfYol2oAvIm0ZBUQJrnDfnZC0CBmzD5olausiFN03fl67/F+FofMmRW5BPoOgwi9kLEohTAsp3cxW1XbUEs9ZdNvgjjw9yN+4wn6RoYQr0bLR/DNmBYvVAzlCDlEuqULpy3W/fVXlYqkzziAebwunE+ueWUPivUeg1S9Q8WkAX1yGSkGHI3/mWA1cYTnlP+mRFSA4BROwufc2PRAkEG7+GZwGPCE8xRttfKjwkBC6pgTnh0yAf10w4KFAHEocwI39eQWfuZ0UwC/T9S7PaShpusTcrhNLKKk6LVD0rP8Ahpyu7Gx8+J/tFHgHs+ASf2VSp9uCoIA6gs0/BeMkIMUjsbtgVcfe4kqfX/eZ9WUnHFnC6V8ucVNAYGU1SASkBvE0p7rZTRTH1N9gdNkxde7Y1QUL+g7WMwTPJCe3ZlVNXREtScijGajVDmTtLr/lNa8P8a4BomRQjuhF2YPV+EETEE16yxNt/w5DnTHb+4C+9ibBww89qng2+CArxx/jfT6UcX08a8rFBlel3IvYytOdYmrBdMmJ9i+FVZi6JuA0Gm/BiNnCVV+endfG5wvdquJIanaBkrOuHlgUNobWU7QkiMqbPNz8j9D1aZK4LXwYwv9rU67KpuGAYCLiOIC1SZQuKHynFrbY8WL/1kSnp1g5OkePUKNI6Jl5auMBbynzKpOYCRA/rVqrtQkaBchj86GiDdUUyvLckzOQ4AzkXPHpLpTZ1ufX/rmfRjFJqnsZAL4+GYNJnlmuwHdELxG05PENGi706yTq32Gm91Qf0PwalFgCWl0vOZp7eClfUV/OxLI/jyFimypOBlp2AK+BoE6PWHzo0ngeq420/wOFrrnHYFZ+IuA/1v0z2HJV6f0Il+DtAq/pO+1AjmazWlrwTPuQb1SaPhOHL/T8LmIwEt+fYLDvH96AvkI9xOzYrHPE0XX5phPCZzvWP4hYIU77JuyTqjDlS1UlLTuL+uJCa2gtKXKmaKUjU2tk0zT2+i9Iz4msUoU9QsAztVwUhBXiQh6D4OZmb5DgiFqLYP2nAo7p+jP4fl9j48hTPVknoBh0Ip8RBmv+GR2/Z9WxXOjVuX/rNRmc3U+0NIiem/WfybfvlZSZJ3riZHKAA8OnWJqwH6S7U1lk0cpII/84zv45nYh/YdjY+kJHghefHzm7SS/cslTxrHnUVzG/meo/nKcP8kyYuYYRhsoNGHDaKmOsw+FKGgGsjmjlRjvuIlmpESz9E4HKCFSU5ANr5nEk5nv9MFsWrWH46LisitYq+j2+uehZ8f7jj32it+DnYFtnLZjxF/XjWAuVk532bD3FsAnlWD37KMEgQERZhdh8LJQrL6fA8dBh13ry9nsJUk/2Z60u2A+BPtxVgzceJIQR0+gO6CleTqwv7aWVVd9IeGNmxUeIuDDGHdIUNLeefChPrCURVi7HQTFzo/QdPAhOeXy0MDfz8YHz/luK29QFtHwaAR4wRPmqsX6eViFuFB7h0+t/60pIqZQBKh2BbfriBircaQ4m41bjZT5x62POq2sh5El1b+yUlMPqK9G7KLV6fO+YrPmfl2+oYucDnK7P41gewjanOibs+/gaFLIi4pY7Ehesir/3Q1IzPjTpA3/n72pZlyjRxxN1Q/zkoW9+a9PY4dKEOBS8E8UlTcN419oQ9hyW1Kx4nHNIdUOOJIqKNS6t5YYdwXMuvVGCva7wZCgGxpZpvoCttaEqzHYBlmXaZeU/m0d+tgcMfzFfq/u9TvGd+H6Cog9qfaz9zt0iEH8WjeV9PDOFTfyHLTe/jmBWzOTAglSrL7jTVplL5sVe+Zg6Q5NKfMEDRcgVPNDMHIehbb1KoopBgw55gpFNYnhFrLmjftEw0fj0x7sfsfFz8oB5eH4JMFoCy9C54ajx+nc/UWxQrLxvfqf+BA+DdSn8qFeWhzBVQvEX8PkLKBCKyf4dLRAb2AV5L8hBnjjO702FC01XJ003KSWVvMuA2vliwIG0HOegxDZBnMzlJuq1j/inrtY84UuGvgl982aVrPjhZ0XApuSVMOL0HrVGFWuewb+HgJAFr9p9eCskgjyTt/QSAHdU0LaamkJyrJoEaepK31lD8FdoLE0WyrbAgf0BDYfX8BCwgeCCKtmp8+au/GByZKyBMWPr1EQArOlQyqiHvA2W8RUSmsWZhU/hzBrrmMZsmdOkioyAH9SjZf5/WdAVOlzivqvvYyFyaS/6fJVqYyHjaBG7LUiqaySohGTN8YnpfrDFXvN/ZP2WO5PbJ2kCHykr5k1Al3QjNAhbOQDuXRaf0oogN2PSgtSBxQUIT/c4em6p3X0nS/rsAdDdQXF7+/RyNLOWUjXQJr+A6WcDFFf5gf4wcUKYRbA0uBBPni1sWHnii3P33CKyJMTso/wHMoOQ5puu6imgvirLbK2fr74Al0gI3xrb9NuSU8gsttoRH6L4Ana0mbB+RGQxptN9wzVrlSZLScAcckdnS/jCfJsUKDlhpytqmejVG4kK29iFVoAMcPt+W6MrGRL2UV9wj8HAOMthNd7xyBpkxwDlZGPb0wJQwFO/K7bAy2nOXbnRDU7FL/Gi+H7qBgECxO2JkL8rofcz6vuXAAT0rSAogrQAQY05fQUkK9ywK5SWyLZKxCd5Uo9/fooDO3wWSyHq6DxiKmrexFkKQFKEs1qMyAafJDzyPQSUncY8lXKb46TsnugQYYR5gyLaaj3H65ZTgPyPpRLVoqxsJxTqbyb45325ktK8gMaDqvw4MlrIzA2P0FnnDbwwybcaanxM7YmJ/HzmC43+eDX9guciyD8und3mIrCHa9tqTC3sMMdKexLML9K43g4JK5pIdXeowjzK1iYwnO/bw03DzYSd8jLfs1XpWLhU7oSxtYrovG31BTxMs2ZYmuCDrcIUCu4NmMyM2pLoDdk9v+BW1gAl8dyR6+x8/YkG1QJKHkmnHuLmWxch2x1YL28GMQOenjjDlHh7v82xtLUlNR9KYoK4EHUoZOhnenKrbmlhphmIC82+pcW5WHMAfPJ++SyplMkIgtFqV4kSmpphjDx1tP+iPIgyaBOJNieog/Kzs/NBBjBfeoXLoDHA9dyj4854t7kD1LAqead2JqkRDjV3g34uYkyU3zvie1DlNahWncSxk8L07lhANYgNnQ3IQSPsrVXkbDwrk1Xj+ajeCk7//6DhnDhOHmtZGz13tA4pqOcmIDHO7eODaj7doIvETCxbVh7IuxbMJFvD+BabZfVg/oaYuiXuDsz3OD0e/T3TtsBajP8Qih3/FbSwhUhHY0cL1KTvoD00UjWJIM8XN+/rXRQmqI1oQm6aUwCu6440ZnMnijhoofe+hf/wOzs0Bp2txKVFiqobz0zpb4O7Y7G7EK8lujfCDk+MmOvJ1i267hn24/9Pcn0SW30SRQnOZN75oVT2pclvhINyj0mOTJ/XCbNcRU4pxf1YgaOOR88hXPa60TDiXCaV2a39c9vrUXpN23NZyJ/KO5ytrXD8K9JdeCTGAHWunVcU9vfLPfKNTPeWykaQH215b9l5uNtCplYOKG4bYMrYmmcAKMRB79tPKoT7vi7zPRy638KAFZLxTzS02NmqWy9XqtSYflmtWrxi3kyCCFoXTDNLdetiMyZOhzzO93/658opYxJmdi79JjXsuIFcqJGH94T95EXa/lKLDSYQxntbzqVtTzSBb5PwRzr9wnazKWqdgUNJCZHgcpEmkDjIa1wirnkS/owyOU3my2MT1wqjovWEwMDMsk+mi8AOCLYnBoCIkSAPw1NJf0iywdnRdCSOvcbvoEV7svva6qWYPaJF/HtcxT6a3iTpECSj2s8Rb//NcPtD3xcOm5X3raXq5J1R2zXVY9P+FcH3gUwP0+2DOKvPqQWAP+4Sq1UC5OFYjFuv2WzHVBUfMQfv3RJrS9Jf9rRl9VRTzb4XtQf1pZ2mb2geuYeFNhPMyOxSo5RyKLzBDGlnXo7TxRcKC8GTW2XC1sPFADf6GZjEa1qYyEDP+MMf9fKpustscNKTyC7E5pz1NyN6VGc+0BHxa+AgyLXObmubyCgyjSiPmP52S+ZyAABZuNpiiemsLNy98DhlwfAXbnHaTVKfDxA9ljgJ4mgYTtxSfFz6oBXi+c+l+0f9FCLWLIGaomWgcfg810xQe+9zwV/7lvLAvdj3PbZN5hQVR3jm45/ViKq9ZD5X2JFbONewE8CGnXe5A/gx7dmjyR53HsVsPw0OpeoJDmH/qdHB8JyH4NGR6mQKnC2URQEpgSurMQy9S9TYAAAA==" alt="Logo Inspektorat Jabar" style="width:100%;height:100%;object-fit:contain;"></div>

      <div class="judul">
        <div class="t1">i-Finance</div>
        <div class="t2">Inspektorat Daerah<br>Provinsi Jawa Barat</div>
      </div>
      <div class="sb-collapse" id="sb-collapse-btn" title="Sembunyikan menu"><svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg></div>
      <div class="sb-close" id="sb-close"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></div>
    </div>
    <nav class="sb-menu">
      @php
        $currentRole = \App\Helpers\GuestSession::role();
        $akses = config('akses.menu')[$currentRole] ?? [];
        $activeNav = trim($__env->yieldContent('activeNav'));
        $navHref = [
            'dashboard' => route('dashboard.index'),
            'sp-input' => $currentRole === 'layanan' ? route('sp.input.create') : route('surat-perintah.create'),
            'sp-data' => route('surat-perintah.index'),
            'sp-monitor' => route('surat-perintah.monitoring'),
            'sp-cetakspj' => route('cetak-spj.index'),
            'audit-log' => route('audit-log.index'),
            'npd' => route('npd.index'),
            'npd-data' => route('npd.data'),
            'persetujuan' => route('npd.persetujuan'),
            'verifikasi' => route('npd.verifikasi'),
            'users' => route('users.index'),
            'pelimpahan' => route('pelimpahan.index'),
            'manajemen-data' => route('manajemen-data.index'),
            'rincian' => route('rincian.index'),
            'dashpd' => route('dashboard.perjalanan.index'),
            'dashspj' => route('dashboard.spj.index'),
            'dash-tk' => route('tunjangan.dashboard'),
            'tk-pegawai' => route('tunjangan.pegawai.index'),
            'tk-data' => route('tunjangan.data.index'),
            'tk-monitor' => route('tunjangan.monitoring'),
            'tk-form' => route('tunjangan.form'),
            'invspj' => route('inventarisasi-spj.index'),
            'pengembalian-create' => route('pengembalian.create'),
            'pengembalian' => route('pengembalian.index'),
            'sp-cetaksppd' => route('segera.sp-cetaksppd'),
            'gt-gaji' => route('segera.gt-gaji'),
            'gt-beban' => route('segera.gt-beban'),
            'gt-kondisi' => route('segera.gt-kondisi'),
            'gt-total' => route('segera.gt-total'),
            'gt-cetak' => route('segera.gt-cetak'),
            'gt-daftar' => route('segera.gt-daftar'),
            'profil' => route('profil.show'),
        ];
        $href = fn ($key) => $navHref[$key] ?? '#';
        $group = function (array $subs) use ($akses, $activeNav) {
            return [
                'visible' => (bool) array_intersect($subs, $akses),
                'open' => in_array($activeNav, $subs, true),
            ];
        };
      @endphp

      @php($g = $group(['dashboard', 'dashpd', 'dash-tk', 'dashspj']))
      @if ($g['visible'])
      <div class="sb-group{{ $g['open'] ? ' open' : '' }}">
        <div class="sb-item sb-parent" id="nav-dashboard-parent">
          <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
          Dashboard
          <svg class="chev" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
        <div class="sb-sub">
          @if (in_array('dashboard', $akses)) <a class="sb-item sub{{ $activeNav === 'dashboard' ? ' active' : '' }}" href="{{ $href('dashboard') }}">Dashboard Realisasi Anggaran</a> @endif
          @if (in_array('dashpd', $akses)) <a class="sb-item sub{{ $activeNav === 'dashpd' ? ' active' : '' }}" href="{{ $href('dashpd') }}">Dashboard Perjalanan Dinas</a> @endif
          @if (in_array('dash-tk', $akses)) <a class="sb-item sub{{ $activeNav === 'dash-tk' ? ' active' : '' }}" href="{{ $href('dash-tk') }}">Dashboard Tunjangan Keluarga</a> @endif
          @if (in_array('dashspj', $akses)) <a class="sb-item sub{{ $activeNav === 'dashspj' ? ' active' : '' }}" href="{{ $href('dashspj') }}">Dashboard SPJ Perjalanan Dinas</a> @endif
        </div>
      </div>
      @endif

      @if (in_array('rincian', $akses))
      <a class="sb-item{{ $activeNav === 'rincian' ? ' active' : '' }}" href="{{ $href('rincian') }}">
        <svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        Rincian Realisasi
      </a>
      @endif

      @php($analisisVisible = in_array('analisis', $akses, true))
      @php($analisisOpen = in_array($activeNav, ['tren-realisasi', 'simulasi-pergeseran'], true))
      @if ($analisisVisible)
      <div class="sb-group{{ $analisisOpen ? ' open' : '' }}">
        <div class="sb-item sb-parent" id="nav-analisis-parent">
          <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><line x1="3" y1="20" x2="21" y2="20"/></svg>
          Analisis dan Tren
          <svg class="chev" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
        <div class="sb-sub">
          <a class="sb-item sub{{ $activeNav === 'tren-realisasi' ? ' active' : '' }}" href="{{ route('analisis.index') }}">Tren Realisasi</a>
          <a class="sb-item sub{{ $activeNav === 'simulasi-pergeseran' ? ' active' : '' }}" href="{{ route('simulasi-anggaran.index') }}">Simulasi Pergeseran/Perubahan</a>
        </div>
      </div>
      @endif

      {{-- Pembuatan, Persetujuan, dan Verifikasi NPD dikelompokkan sebagai
           sub menu satu modul, bersama Data NPD yang baru. --}}
      @php($g = $group(['npd-data', 'npd', 'persetujuan', 'verifikasi']))
      @if ($g['visible'])
      <div class="sb-group{{ $g['open'] ? ' open' : '' }}">
        <div class="sb-item sb-parent" id="nav-npd-parent">
          <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/></svg>
          Nota Pencairan Dana (NPD)
          <svg class="chev" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
        <div class="sb-sub">
          @if (in_array('npd-data', $akses)) <a class="sb-item sub{{ $activeNav === 'npd-data' ? ' active' : '' }}" href="{{ $href('npd-data') }}">Data NPD</a> @endif
          @if (in_array('npd', $akses)) <a class="sb-item sub{{ $activeNav === 'npd' ? ' active' : '' }}" href="{{ $href('npd') }}">Pembuatan NPD</a> @endif
          @if (in_array('persetujuan', $akses)) <a class="sb-item sub{{ $activeNav === 'persetujuan' ? ' active' : '' }}" href="{{ $href('persetujuan') }}">Persetujuan NPD</a> @endif
          @if (in_array('verifikasi', $akses)) <a class="sb-item sub{{ $activeNav === 'verifikasi' ? ' active' : '' }}" href="{{ $href('verifikasi') }}">Verifikasi NPD</a> @endif
        </div>
      </div>
      @endif

      @php($g = $group(['pengembalian-create', 'pengembalian']))
      @if ($g['visible'])
      <div class="sb-group{{ $g['open'] ? ' open' : '' }}">
        <div class="sb-item sb-parent" id="nav-pengembalian-parent">
          <svg viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><polyline points="3 3 3 8 8 8"/></svg>
          Pengembalian
          <svg class="chev" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
        <div class="sb-sub">
          @if (in_array('pengembalian-create', $akses)) <a class="sb-item sub{{ $activeNav === 'pengembalian-create' ? ' active' : '' }}" href="{{ $href('pengembalian-create') }}">Input Data Pengembalian</a> @endif
          @if (in_array('pengembalian', $akses)) <a class="sb-item sub{{ $activeNav === 'pengembalian' ? ' active' : '' }}" href="{{ $href('pengembalian') }}">Daftar Pengembalian</a> @endif
        </div>
      </div>
      @endif

      @if (in_array('invspj', $akses))
      <a class="sb-item{{ $activeNav === 'invspj' ? ' active' : '' }}" href="{{ $href('invspj') }}">
        <svg viewBox="0 0 24 24"><path d="M4 4h16v6H4z"/><path d="M4 10h16v10H4z"/><line x1="9" y1="14" x2="15" y2="14"/></svg>
        Inventarisasi SPJ
      </a>
      @endif

      @php($spmVisible = in_array('spm', $akses, true))
      @php($spmOpen = in_array($activeNav, ['spm-upgu', 'spm-ls'], true))
      @if ($spmVisible)
      <div class="sb-group{{ $spmOpen ? ' open' : '' }}">
        <div class="sb-item sb-parent" id="nav-spm-parent">
          <svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="2" y1="9" x2="22" y2="9"/><line x1="6" y1="14" x2="10" y2="14"/></svg>
          Data Realisasi SP2D
          <svg class="chev" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
        <div class="sb-sub">
          <a class="sb-item sub{{ $activeNav === 'spm-upgu' ? ' active' : '' }}" href="{{ route('spm.up-gu.index') }}">Realisasi SP2D UP/GU/TU</a>
          <a class="sb-item sub{{ $activeNav === 'spm-ls' ? ' active' : '' }}" href="{{ route('spm.ls.index') }}">Realisasi SP2D LS</a>
        </div>
      </div>
      @endif

      @php($g = $group(['sp-input', 'sp-data', 'sp-monitor', 'sp-cetakspj', 'sp-cetaksppd']))
      @if ($g['visible'])
      <div class="sb-group{{ $g['open'] ? ' open' : '' }}">
        <div class="sb-item sb-parent" id="nav-sp-parent">
          <svg viewBox="0 0 24 24"><path d="M9 2h6a2 2 0 0 1 2 2v0a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2v0a2 2 0 0 1 2-2z"/><path d="M9 4H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-3"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="15" y2="16"/></svg>
          Surat Perintah
          <svg class="chev" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
        <div class="sb-sub">
          @if (in_array('sp-input', $akses)) <a class="sb-item sub{{ $activeNav === 'sp-input' ? ' active' : '' }}" href="{{ $href('sp-input') }}">Input SP</a> @endif
          @if (in_array('sp-data', $akses)) <a class="sb-item sub{{ $activeNav === 'sp-data' ? ' active' : '' }}" href="{{ $href('sp-data') }}">Data SP</a> @endif
          @if (in_array('sp-monitor', $akses)) <a class="sb-item sub{{ $activeNav === 'sp-monitor' ? ' active' : '' }}" href="{{ $href('sp-monitor') }}">Monitoring SP</a> @endif
          @if (in_array('sp-cetakspj', $akses)) <a class="sb-item sub{{ $activeNav === 'sp-cetakspj' ? ' active' : '' }}" href="{{ $href('sp-cetakspj') }}">Cetak SPJ Perjalanan Dinas</a> @endif
          @if (in_array('sp-cetaksppd', $akses)) <a class="sb-item sub{{ $activeNav === 'sp-cetaksppd' ? ' active' : '' }}" href="{{ $href('sp-cetaksppd') }}">Cetak SPPD</a> @endif
        </div>
      </div>
      @endif

      @php($g = $group(['tk-pegawai', 'tk-data', 'tk-form', 'tk-monitor']))
      @if ($g['visible'])
      <div class="sb-group{{ $g['open'] ? ' open' : '' }}">
        <div class="sb-item sb-parent" id="nav-tk-parent">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Data Kepegawaian
          <svg class="chev" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
        <div class="sb-sub">
          @if (in_array('tk-pegawai', $akses)) <a class="sb-item sub{{ $activeNav === 'tk-pegawai' ? ' active' : '' }}" href="{{ $href('tk-pegawai') }}">Data Pegawai</a> @endif
          @if (in_array('tk-data', $akses)) <a class="sb-item sub{{ $activeNav === 'tk-data' ? ' active' : '' }}" href="{{ $href('tk-data') }}">Data Tunjangan Keluarga</a> @endif
          @if (in_array('tk-form', $akses)) <a class="sb-item sub{{ $activeNav === 'tk-form' ? ' active' : '' }}" href="{{ $href('tk-form') }}">Perubahan Data</a> @endif
          @if (in_array('tk-monitor', $akses)) <a class="sb-item sub{{ $activeNav === 'tk-monitor' ? ' active' : '' }}" href="{{ $href('tk-monitor') }}">Monitoring Pengajuan</a> @endif
        </div>
      </div>
      @endif

      {{-- Gaji dan Tunjangan: rumahnya sudah dibuat, isinya menyusul.
           Semua sub menu masih diarahkan ke halaman "Under Progress". --}}
      @php($g = $group(['gt-gaji', 'gt-beban', 'gt-kondisi', 'gt-total', 'gt-cetak', 'gt-daftar']))
      @if ($g['visible'])
      <div class="sb-group{{ $g['open'] ? ' open' : '' }}">
        <div class="sb-item sb-parent" id="nav-gt-parent">
          <svg viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><line x1="6" y1="12" x2="6.01" y2="12"/><line x1="18" y1="12" x2="18.01" y2="12"/></svg>
          Gaji dan Tunjangan
          <svg class="chev" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
        <div class="sb-sub">
          @if (in_array('gt-gaji', $akses)) <a class="sb-item sub{{ $activeNav === 'gt-gaji' ? ' active' : '' }}" href="{{ $href('gt-gaji') }}">Gaji Induk</a> @endif
          @if (in_array('gt-beban', $akses)) <a class="sb-item sub{{ $activeNav === 'gt-beban' ? ' active' : '' }}" href="{{ $href('gt-beban') }}">TPP Beban Kerja</a> @endif
          @if (in_array('gt-kondisi', $akses)) <a class="sb-item sub{{ $activeNav === 'gt-kondisi' ? ' active' : '' }}" href="{{ $href('gt-kondisi') }}">TPP Kondisi Kerja</a> @endif
          @if (in_array('gt-total', $akses)) <a class="sb-item sub{{ $activeNav === 'gt-total' ? ' active' : '' }}" href="{{ $href('gt-total') }}">Total Penghasilan</a> @endif
          @if (in_array('gt-cetak', $akses)) <a class="sb-item sub{{ $activeNav === 'gt-cetak' ? ' active' : '' }}" href="{{ $href('gt-cetak') }}">Cetak Rincian Penghasilan</a> @endif
          @if (in_array('gt-daftar', $akses)) <a class="sb-item sub{{ $activeNav === 'gt-daftar' ? ' active' : '' }}" href="{{ $href('gt-daftar') }}">Daftar Rincian Penghasilan</a> @endif
        </div>
      </div>
      @endif

      @if (in_array('audit-log', $akses))
      <a class="sb-item{{ $activeNav === 'audit-log' ? ' active' : '' }}" href="{{ $href('audit-log') }}">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Log Aktivitas
      </a>
      @endif

      @php($g = $group(['pelimpahan', 'users', 'manajemen-data']))
      @if ($g['visible'])
      <div class="sb-group{{ $g['open'] ? ' open' : '' }}">
        <div class="sb-item sb-parent" id="nav-setting-parent">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
          Setting
          <svg class="chev" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
        <div class="sb-sub">
          @if (in_array('pelimpahan', $akses)) <a class="sb-item sub{{ $activeNav === 'pelimpahan' ? ' active' : '' }}" href="{{ $href('pelimpahan') }}">Pelimpahan</a> @endif
          @if (in_array('manajemen-data', $akses)) <a class="sb-item sub{{ $activeNav === 'manajemen-data' ? ' active' : '' }}" href="{{ $href('manajemen-data') }}">Manajemen Data</a> @endif
          @if (in_array('users', $akses)) <a class="sb-item sub{{ $activeNav === 'users' ? ' active' : '' }}" href="{{ $href('users') }}">Manajemen Users</a> @endif
        </div>
      </div>
      @endif

      @if (in_array('profil', $akses))
      <a class="sb-item{{ $activeNav === 'profil' ? ' active' : '' }}" href="{{ $href('profil') }}">
        <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Profil Saya
      </a>
      @endif

    </nav>
    <div style="margin-top:auto;">
      <div id="sb-userinfo" style="padding:8px 20px;font-size:11.5px;color:#9db8d6;border-top:1px solid rgba(255,255,255,.1);">{{ auth()->user()->nama ?? 'Pengguna Layanan' }} &mdash; {{ config('akses.role_label')[$currentRole] ?? $currentRole }}</div>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="sb-logout" style="width:100%;border:none;background:none;font:inherit;text-align:left;cursor:pointer;">
          <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          <span>Keluar</span>
        </button>
      </form>
    </div>
  </aside>

  <div class="main">
    @php($namaPengguna = auth()->user()->nama ?? 'Pengguna Layanan')
    @php($sapaan = auth()->user()?->sapaan() ?? 'Pak/Bu')
    {{-- Yang disapa adalah nama panggilan; nama lengkap tetap dipakai sebagai
         identitas akun di menu profil. --}}
    @php($namaSapaan = auth()->user()?->namaSapaan() ?: $namaPengguna)
    <div class="topbar">
      <div class="burger" id="sb-burger"><svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></div>

      <div class="tb-ketik"><span id="tb-teks"></span><span class="kursor" aria-hidden="true"></span></div>

      <div class="tb-kanan">
        <span class="tb-tahun">Tahun Anggaran {{ config('anggaran.tahun_aktif') }}</span>

        <button type="button" class="tb-ikon tb-tema" id="tb-tema" title="Ganti mode terang/gelap" aria-label="Ganti mode terang/gelap">
          <svg class="matahari" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4.2"/><line x1="12" y1="2" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="22"/><line x1="2" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="22" y2="12"/><line x1="4.9" y1="4.9" x2="6.3" y2="6.3"/><line x1="17.7" y1="17.7" x2="19.1" y2="19.1"/><line x1="4.9" y1="19.1" x2="6.3" y2="17.7"/><line x1="17.7" y1="6.3" x2="19.1" y2="4.9"/></svg>
          <svg class="bulan" viewBox="0 0 24 24"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
        </button>

        <div class="tb-profil">
          <button type="button" class="tb-avatar" id="tb-avatar" aria-haspopup="true" aria-expanded="false" title="{{ $namaPengguna }}">
            {{ \Illuminate\Support\Str::of($namaPengguna)->trim()->substr(0, 1)->upper() }}
          </button>
          <div class="tb-menu" id="tb-menu" role="menu">
            <div class="nm">
              <b>{{ $namaPengguna }}</b>
              <span>{{ config('akses.role_label')[$currentRole] ?? $currentRole }}</span>
            </div>
            @if (in_array('profil', $akses))
              <a href="{{ $href('profil') }}" role="menuitem">
                <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Profil Saya
              </a>
            @endif
            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button type="submit" role="menuitem">
                <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Keluar
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <div class="page show">
      @yield('content')
    </div>
  </div>
</div>

<script>
  document.querySelectorAll('.sb-parent').forEach(function (parent) {
    parent.addEventListener('click', function () {
      parent.closest('.sb-group').classList.toggle('open');
    });
  });

  (function () {
    var KUNCI = 'ifinance-sidebar-collapsed';
    var akar = document.documentElement;

    function setKecil(kecil) {
      akar.classList.toggle('sidebar-collapsed', kecil);
      try { localStorage.setItem(KUNCI, kecil ? '1' : '0'); } catch (e) {}
    }

    var tombolKecil = document.getElementById('sb-collapse-btn');
    if (tombolKecil) tombolKecil.addEventListener('click', function () { setKecil(true); });

    // Logo di puncak rel: tombol untuk membentangkan sidebar kembali.
    var logo = document.getElementById('sb-logo');
    if (logo) {
      logo.addEventListener('click', function () {
        if (akar.classList.contains('sidebar-collapsed')) setKecil(false);
      });
      logo.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); logo.click(); }
      });
    }

    // Saat mengecil, mengeklik ikon mana pun membentangkan sidebar dulu -
    // bukan langsung berpindah halaman. Ikonnya sendiri tanpa label, jadi
    // menavigasi dari sana gampang salah sasaran.
    var menu = document.querySelector('.sb-menu');
    if (menu) {
      menu.addEventListener('click', function (e) {
        if (!akar.classList.contains('sidebar-collapsed')) return;
        if (window.innerWidth < 841) return;
        if (!e.target.closest('.sb-item')) return;
        e.preventDefault();
        e.stopPropagation();
        setKecil(false);
      }, true);
    }

    var burger = document.getElementById('sb-burger');
    var tombolTutup = document.getElementById('sb-close');
    var tirai = document.getElementById('sb-overlay');
    var sidebar = document.querySelector('.sidebar');
    function bukaPonsel() { sidebar.classList.add('open'); tirai.classList.add('show'); }
    function tutupPonsel() { sidebar.classList.remove('open'); tirai.classList.remove('show'); }
    if (burger) burger.addEventListener('click', bukaPonsel);
    if (tombolTutup) tombolTutup.addEventListener('click', tutupPonsel);
    if (tirai) tirai.addEventListener('click', tutupPonsel);
  })();

  /* ===== Bilah atas: sakelar tema, menu profil, dan teks berjalan ===== */
  (function () {
    var akar = document.documentElement;
    var KUNCI_TEMA = 'ifinance-tema';

    var tombolTema = document.getElementById('tb-tema');
    if (tombolTema) {
      tombolTema.addEventListener('click', function () {
        var gelap = akar.getAttribute('data-tema') === 'gelap';
        if (gelap) akar.removeAttribute('data-tema');
        else akar.setAttribute('data-tema', 'gelap');
        try { localStorage.setItem(KUNCI_TEMA, gelap ? 'terang' : 'gelap'); } catch (e) {}
      });
    }

    var avatar = document.getElementById('tb-avatar');
    var menuProfil = document.getElementById('tb-menu');
    if (avatar && menuProfil) {
      avatar.addEventListener('click', function (e) {
        e.stopPropagation();
        var buka = menuProfil.classList.toggle('buka');
        avatar.setAttribute('aria-expanded', buka ? 'true' : 'false');
      });
      document.addEventListener('click', function (e) {
        if (menuProfil.contains(e.target)) return;
        menuProfil.classList.remove('buka');
        avatar.setAttribute('aria-expanded', 'false');
      });
      document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        menuProfil.classList.remove('buka');
        avatar.setAttribute('aria-expanded', 'false');
      });
    }

    /* Efek ketik berulang: tulis, tahan sebentar, hapus, lanjut kalimat
       berikutnya. Berhenti total bila pengguna meminta gerakan dikurangi. */
    var wadah = document.getElementById('tb-teks');
    if (!wadah) return;

    var kalimat = @json(['i-Finance - Inspektorat Daerah Provinsi Jawa Barat', 'Selamat Datang, '.$sapaan.' '.$namaSapaan.'!']);

    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      wadah.textContent = kalimat[0];
      return;
    }

    var iKalimat = 0, iHuruf = 0, menghapus = false;

    function langkah() {
      var teks = kalimat[iKalimat];
      iHuruf += menghapus ? -1 : 1;
      wadah.textContent = teks.slice(0, iHuruf);

      var jeda = menghapus ? 34 : 62;

      if (!menghapus && iHuruf === teks.length) {
        menghapus = true;
        jeda = 1900;
      } else if (menghapus && iHuruf === 0) {
        menghapus = false;
        iKalimat = (iKalimat + 1) % kalimat.length;
        jeda = 420;
      }

      setTimeout(langkah, jeda);
    }

    langkah();
  })();
</script>

@include('layouts.partials.select-cari')
@include('layouts.partials.input-rupiah')
</body>
</html>
