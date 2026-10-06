<#
.SYNOPSIS
    Genera un ZIP listo para subir a cPanel (el servidor no necesita Node).

.DESCRIPTION
    1. Compila el frontend (npm run build) en esta máquina.
    2. Copia el proyecto a una carpeta temporal sin node_modules, tests, .env ni archivos subidos locales.
    3. Instala dependencias PHP de producción (composer --no-dev, autoloader optimizado).
    4. Comprime todo en dist\mistiup-AAAAMMDD-HHMM.zip

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts\empaquetar.ps1
#>

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$stamp = Get-Date -Format 'yyyyMMdd-HHmm'
$staging = Join-Path $env:TEMP "mistiup-release-$stamp"
$dist = Join-Path $root 'dist'
$zip = Join-Path $dist "mistiup-$stamp.zip"

Push-Location $root
try {
    Write-Host '1/4 Compilando frontend...' -ForegroundColor Cyan
    & npm.cmd run build
    if ($LASTEXITCODE -ne 0) { throw 'Falló npm run build.' }

    Write-Host '2/4 Copiando archivos...' -ForegroundColor Cyan
    $excludeDirs = @('node_modules', 'vendor', 'tests', 'dist', '.git', '.claude', '.idea', '.vscode', 'scripts',
        (Join-Path $root 'resources\js'), (Join-Path $root 'resources\css'))
    $excludeFiles = @('.env', '.env.backup', '.env.testing', 'hot', 'database.sqlite', '*.log', '.phpunit.result.cache')
    & robocopy $root $staging /E /NFL /NDL /NJH /NJS /NP /XD $excludeDirs /XF $excludeFiles | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "Falló robocopy ($LASTEXITCODE)." }

    # Carpetas de runtime vacías (Laravel las necesita pero su contenido es local).
    # storage\fonts es el caché de dompdf: guarda rutas absolutas de esta máquina y se regenera en el servidor.
    foreach ($dir in 'storage\app\private', 'storage\app\public', 'storage\framework\cache\data', 'storage\framework\sessions',
        'storage\framework\views', 'storage\logs', 'storage\fonts', 'storage\inertia-devtools', 'bootstrap\cache') {
        $path = Join-Path $staging $dir
        if (Test-Path $path) { Get-ChildItem $path -Force | Where-Object { $_.Name -ne '.gitignore' } | Remove-Item -Recurse -Force }
        else { New-Item -ItemType Directory -Force $path | Out-Null }
    }

    Write-Host '3/4 Instalando dependencias PHP de producción...' -ForegroundColor Cyan
    Push-Location $staging
    & composer install --no-dev --optimize-autoloader --no-interaction --no-progress
    if ($LASTEXITCODE -ne 0) { throw 'Falló composer install.' }
    Pop-Location

    Write-Host '4/4 Comprimiendo...' -ForegroundColor Cyan
    New-Item -ItemType Directory -Force $dist | Out-Null
    # tar.exe (incluido en Windows 10+) usa "/" en las rutas del ZIP; Compress-Archive de PowerShell 5.1 usa "\"
    # y el administrador de archivos de cPanel crearía nombres rotos al descomprimir.
    & tar.exe -a -c -f $zip -C $staging .
    if ($LASTEXITCODE -ne 0) { throw 'Falló la compresión.' }
    Remove-Item $staging -Recurse -Force

    $size = [math]::Round((Get-Item $zip).Length / 1MB, 1)
    Write-Host "Listo: $zip ($size MB)" -ForegroundColor Green
    Write-Host 'Siguiente paso: docs\DESPLIEGUE.md' -ForegroundColor Green
}
finally {
    Pop-Location
}
