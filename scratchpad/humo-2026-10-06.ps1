# Humo post-deploy, SOLO LECTURA, visitante nuevo (sin cookies). -Banner exige banner auto-abierto.
param([switch]$Banner)
$ErrorActionPreference = 'Continue'
$Sitio = 'https://www.limaviewtours.com'
$UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130 Safari/537.36'
$script:fallos = 0
function Check([bool]$ok, [string]$msg) { if ($ok) { Write-Host "  OK    $msg" -ForegroundColor Green } else { Write-Host "  FALLO $msg" -ForegroundColor Red; $script:fallos++ } }
$pags = @('/es', '/en', '/pt', '/es/tours/detalle/city-tour-cusco')
foreach ($p in $pags) {
    Write-Host "== $p"
    $f = Join-Path $env:TEMP ('humo-' + ($p -replace '[^a-z0-9]', '_') + '.html')
    $code = & curl.exe -sS -A $UA -o $f -w '%{http_code}' "$Sitio$p"
    Check ($code -eq '200') "status 200 (obtenido $code)"
    $h = if (Test-Path $f) { [IO.File]::ReadAllText($f) } else { '' }
    Check ($h.Length -gt 5000) "HTML no vacio ($($h.Length) bytes)"
    $mc = [regex]::Match($h, "consent'\s*,\s*'default'")
    Check $mc.Success "contiene consent default"
    $mg = [regex]::Match($h, 'gtm\.js')
    if ($mg.Success) { Check ($mc.Success -and $mc.Index -lt $mg.Index) "consent default (pos $($mc.Index)) ANTES de la primera mencion de gtm.js (pos $($mg.Index))" } else { Write-Host "  (sin mencion de gtm.js: Setting seo_gtm_id vacio)" }
    $tag = [regex]::Matches($h, '<script[^>]+src=["''][^"'']*googletagmanager\.com/gtm\.js')
    Check ($tag.Count -eq 0) "ningun <script src=...gtm.js> estatico (hallados $($tag.Count))"
    Check ($h -match 'lvt-consent-granted') "GTM/Pixel enganchados al evento lvt-consent-granted (carga tras consentimiento, no inline directa)"
    Check (-not ($h -match 'googletagmanager\.com/ns\.html')) "sin <noscript> de ns.html"
    Check ($h -match 'data-cookie-preferences') "existe data-cookie-preferences"
    Check ($h -match 'id="lvt-cookie-banner"') "banner presente en el DOM (id lvt-cookie-banner)"
    if ($Banner) {
        $m = [regex]::Match($h, 'if \((true|false) && !window\.lvtConsent\.get\(\)\)')
        Check ($m.Success -and $m.Groups[1].Value -eq 'true') "banner auto-abre para visitante nuevo (valor extraido: '$($m.Groups[1].Value)'; vacio = FALLA)"
    }
}
Write-Host "== /admin/login"
$c = & curl.exe -sS -A $UA -o NUL -w '%{http_code}' "$Sitio/admin/login"
Check ($c -eq '200') "status 200 (obtenido $c)"
if ($script:fallos -eq 0) { Write-Host "`nHUMO OK" -ForegroundColor Green; exit 0 } else { Write-Host "`nHUMO CON $($script:fallos) FALLO(S)" -ForegroundColor Red; exit 1 }
