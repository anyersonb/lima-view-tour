# Lima View Tours - deploy consentimiento 2026-10-06 (f34c3e8 -> c4915c7). PowerShell 5.1.
# Modos: Respaldo | Subir | Vistas | Opcache | Restaurar
# Clave FTP: $env:LIMA_FTP_PASS; si falta, lee C:\Users\USUARIO WEBTILIA\.lima-ftp-pass. Nunca se imprime.
param([Parameter(Mandatory = $true)][ValidateSet('Respaldo', 'Subir', 'Vistas', 'Opcache', 'Restaurar')][string]$Modo)
$ErrorActionPreference = 'Continue'
$Repo   = 'G:\laragon\www\lima-tour'
$Lista  = Join-Path $Repo 'scratchpad\lista-2026-10-06.txt'
$Backup = 'G:\laragon\www\deploy-backup-2026-10-06'
$Stage  = 'G:\laragon\www\deploy-stage-2026-10-06'
$Prod   = 'f34c3e8'; $Nuevo = 'c4915c7'
$Ftp    = 'ftp://ftp.limaviewtours.com'
$Base   = '/public_html/limaprogramacion/'
$pass = $env:LIMA_FTP_PASS
if (-not $pass) { $pf = 'C:\Users\USUARIO WEBTILIA\.lima-ftp-pass'; if (Test-Path -LiteralPath $pf) { $pass = (Get-Content -LiteralPath $pf -Raw).Trim() } }
if (-not $pass) { Write-Host 'FALTA la clave FTP (env LIMA_FTP_PASS o .lima-ftp-pass vacio).' -ForegroundColor Red; exit 2 }
$Cred = "limaweb@limaviewtours.com:$pass"
$files = @(Get-Content -LiteralPath $Lista | Where-Object { $_.Trim() -ne '' })
if ($files.Count -ne 6) { Write-Host "La lista debe tener 6 archivos, tiene $($files.Count)." -ForegroundColor Red; exit 2 }

function Export-Git([string]$rev, [string]$rel, [string]$dest) {
    New-Item -ItemType Directory -Force -Path (Split-Path $dest) | Out-Null
    if (Test-Path -LiteralPath $dest) { Remove-Item -LiteralPath $dest -Force }
    & cmd.exe /c "git -C ""$Repo"" show ${rev}:$rel > ""$dest"""
    return ($LASTEXITCODE -eq 0 -and (Test-Path -LiteralPath $dest) -and (Get-Item -LiteralPath $dest).Length -gt 0)
}
function Get-Remote([string]$rel, [string]$dest) {
    New-Item -ItemType Directory -Force -Path (Split-Path $dest) | Out-Null
    if (Test-Path -LiteralPath $dest) { Remove-Item -LiteralPath $dest -Force }
    & curl.exe -sS --connect-timeout 30 -u $Cred -o $dest "$Ftp$Base$rel"
    return ($LASTEXITCODE -eq 0 -and (Test-Path -LiteralPath $dest) -and (Get-Item -LiteralPath $dest).Length -gt 0)
}
function Put-Remote([string]$src, [string]$rel) {
    $rc = 1
    for ($t = 1; $t -le 3 -and $rc -ne 0; $t++) {
        & curl.exe -sS --connect-timeout 30 --ftp-create-dirs -T $src -u $Cred "$Ftp$Base$rel"
        $rc = $LASTEXITCODE; if ($rc -ne 0) { Start-Sleep -Seconds 2 }
    }
    return ($rc -eq 0)
}
function Hash-Bytes([string]$p) { (Get-FileHash -LiteralPath $p -Algorithm SHA256).Hash }
function Hash-LF([string]$p) {
    $b = [IO.File]::ReadAllBytes($p); $s = [Text.Encoding]::UTF8.GetString($b) -replace "`r`n", "`n"
    $sha = [Security.Cryptography.SHA256]::Create(); ([BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($s)))) -replace '-', ''
}
$fallos = 0

if ($Modo -eq 'Respaldo') {
    Remove-Item -LiteralPath (Join-Path $Backup 'DIFF-OK.txt') -Force -ErrorAction SilentlyContinue
    $ref = Join-Path $Stage 'ref-f34c3e8'
    foreach ($rel in $files) {
        $dst = Join-Path $Backup ($rel -replace '/', '\'); $rf = Join-Path $ref ($rel -replace '/', '\')
        if (-not (Get-Remote $rel $dst)) { Write-Host "FALLO descarga (o vacio): $rel" -ForegroundColor Red; $fallos++; continue }
        if (-not (Export-Git $Prod $rel $rf)) { Write-Host "FALLO git show ${Prod}:$rel" -ForegroundColor Red; $fallos++; continue }
        if ((Hash-Bytes $dst) -eq (Hash-Bytes $rf)) { Write-Host "IGUAL a $Prod         $rel" -ForegroundColor Green }
        elseif ((Hash-LF $dst) -eq (Hash-LF $rf)) { Write-Host "IGUAL (solo fin de linea CRLF/LF) $rel" -ForegroundColor Yellow }
        else {
            Write-Host "DIFIERE de $Prod  -> EDICION MANUAL EN PROD: $rel" -ForegroundColor Red; $fallos++
            & git diff --no-index --stat -- $rf $dst | Select-Object -First 5
        }
    }
    if ($fallos -eq 0) { Set-Content -LiteralPath (Join-Path $Backup 'DIFF-OK.txt') -Value (Get-Date -Format s); Write-Host "`nRESPALDO COMPLETO en $Backup y las 6 coinciden con $Prod. Se puede subir." -ForegroundColor Green; exit 0 }
    Write-Host "`nSE DETIENE: $fallos problema(s). NO SUBIR." -ForegroundColor Red; exit 1
}

if ($Modo -eq 'Subir') {
    if (-not (Test-Path -LiteralPath (Join-Path $Backup 'DIFF-OK.txt'))) { Write-Host 'No hay DIFF-OK.txt: corre antes -Modo Respaldo y que salga limpio.' -ForegroundColor Red; exit 2 }
    $head = (& git -C $Repo rev-parse --short $Nuevo).Trim(); if ($head -ne $Nuevo) { Write-Host "Commit $Nuevo no resuelve ($head)." -ForegroundColor Red; exit 2 }
    $out = Join-Path $Stage 'new-c4915c7'
    foreach ($rel in $files) {
        $src = Join-Path $out ($rel -replace '/', '\')
        if (-not (Export-Git $Nuevo $rel $src)) { Write-Host "FALLO git show ${Nuevo}:$rel" -ForegroundColor Red; $fallos++; continue }
        if (Put-Remote $src $rel) { Write-Host "SUBIDO    $rel" } else { Write-Host "FALLO subida $rel" -ForegroundColor Red; $fallos++; continue }
        $chk = Join-Path $Stage ('verif\' + ($rel -replace '/', '\'))
        if ((Get-Remote $rel $chk) -and ((Hash-Bytes $chk) -eq (Hash-Bytes $src))) { Write-Host "VERIFICADO (bytes remotos = $Nuevo) $rel" -ForegroundColor Green }
        else { Write-Host "FALLO verificacion post-subida $rel" -ForegroundColor Red; $fallos++ }
    }
    if ($fallos -eq 0) { Write-Host "`n6/6 subidos y verificados. Siguiente: -Modo Vistas, luego -Modo Opcache." -ForegroundColor Green; exit 0 }
    Write-Host "`n$fallos fallo(s). Repetir -Modo Subir (es idempotente) o -Modo Restaurar." -ForegroundColor Red; exit 1
}

if ($Modo -eq 'Vistas') {
    $lst = & curl.exe -sS --connect-timeout 30 --list-only -u $Cred "$Ftp${Base}storage/framework/views/"
    if ($LASTEXITCODE -ne 0) { Write-Host 'FALLO al listar storage/framework/views/' -ForegroundColor Red; exit 1 }
    $v = @($lst | ForEach-Object { $_.Trim() } | Where-Object { $_ -match '\.php$' })
    if ($v.Count -eq 0) { Write-Host 'El listado salio VACIO (0 vistas compiladas): sospechoso, no lo doy por bueno. Revisar a mano.' -ForegroundColor Red; exit 1 }
    Write-Host "Vistas compiladas encontradas: $($v.Count)"
    foreach ($n in $v) {
        & curl.exe -sS --connect-timeout 30 -u $Cred -Q "DELE ${Base}storage/framework/views/$n" "$Ftp$Base" | Out-Null
        if ($LASTEXITCODE -ne 0) { Write-Host "FALLO borrar $n" -ForegroundColor Red; $fallos++ }
    }
    $lst2 = & curl.exe -sS --connect-timeout 30 --list-only -u $Cred "$Ftp${Base}storage/framework/views/"
    $rest = @($lst2 | Where-Object { $_ -match '\.php$' }).Count
    Write-Host "Borradas: $($v.Count - $fallos). Restantes tras borrar: $rest (pueden reaparecer 1-2 por visitas)."
    if ($fallos -eq 0) { exit 0 } else { exit 1 }
}

if ($Modo -eq 'Opcache') {
    $php = Get-Content -LiteralPath (Join-Path $Repo 'public\opcache-reset.php') -Raw
    if ($php -notmatch "!==\s*'([^']+)'") { Write-Host 'No pude extraer el token de public/opcache-reset.php' -ForegroundColor Red; exit 2 }
    $r = & curl.exe -sS --connect-timeout 30 "https://www.limaviewtours.com/opcache-reset.php?key=$($Matches[1])"
    if (($r -join '') -match '"reset":\s*true') { Write-Host 'OPCACHE RESET OK (reset: true)' -ForegroundColor Green; exit 0 }
    Write-Host 'OPCACHE: respuesta sin "reset": true (404 = el archivo no esta en prod o el token no coincide).' -ForegroundColor Red; exit 1
}

if ($Modo -eq 'Restaurar') {
    foreach ($rel in $files) {
        $src = Join-Path $Backup ($rel -replace '/', '\')
        if (-not (Test-Path -LiteralPath $src)) { Write-Host "NO HAY RESPALDO de $rel" -ForegroundColor Red; $fallos++; continue }
        if (Put-Remote $src $rel) { Write-Host "RESTAURADO $rel" -ForegroundColor Green } else { Write-Host "FALLO restaurar $rel" -ForegroundColor Red; $fallos++ }
    }
    Write-Host 'Ahora: -Modo Vistas y -Modo Opcache.'
    if ($fallos -eq 0) { exit 0 } else { exit 1 }
}
