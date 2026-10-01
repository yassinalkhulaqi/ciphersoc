<html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,Arial,sans-serif;color:#111;font-size:12px;margin:30px}
.header{background:#0b1220;color:#fff;padding:16px 20px;border-radius:6px}
.header h1{margin:0;font-size:22px}.header p{margin:4px 0 0;color:#9fb3c8;font-size:11px}
h2{border-bottom:2px solid #0b1220;padding-bottom:4px;margin-top:24px}
table{width:100%;border-collapse:collapse;margin-top:8px}th,td{border:1px solid #ccc;padding:6px;text-align:left;font-size:11px}
th{background:#eef2f7}.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;color:#fff}
.critical{background:#dc2626}.high{background:#ea580c}.medium{background:#ca8a04}.low{background:#16a34a}.info{background:#2563eb}
.footer{margin-top:30px;color:#666;font-size:10px;border-top:1px solid #ccc;padding-top:8px}
</style></head><body>
<div class="header"><h1>cipherSOC &mdash; @yield('title','Report')</h1><p>Security Operations Center &bull; Generated {{ now()->toDateTimeString() }} UTC</p></div>
@yield('content')
<div class="footer">cipherSOC {{ config('ciphersoc.version') }} &bull; Confidential &mdash; distribution limited to authorized SOC personnel.</div>
</body></html>
