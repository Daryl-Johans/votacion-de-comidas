<#
.SYNOPSIS
    upload_patch.ps1 - Despliegue incremental por FTP a InfinityFree (Sabores de Vallegrande)
.DESCRIPTION
    Sube únicamente los archivos modificados al servidor de producción InfinityFree.
    Las credenciales FTP se leen del archivo .env.local (NO incluido en Git).
    
    Uso:
        .\upload_patch.ps1                   # Modo interactivo: muestra menú de archivos
        .\upload_patch.ps1 -Todo             # Sube TODOS los archivos del proyecto
        .\upload_patch.ps1 -Archivos "api.php","js/app.js"   # Sube archivos específicos

.PARAMETER Todo
    Sube todos los archivos del proyecto de una sola vez (sin confirmación por archivo).
    
.PARAMETER Archivos
    Lista de rutas relativas de archivos a subir (separadas por coma).
    
.EXAMPLE
    .\upload_patch.ps1
    .\upload_patch.ps1 -Todo
    .\upload_patch.ps1 -Archivos "api.php","db_helper.php","js/app.js"
#>

param(
    [switch]$Todo,
    [string[]]$Archivos = @()
)

# ──────────────────────────────────────────────────────────────
#  CONFIGURACIÓN — editar credenciales en .env.local
# ──────────────────────────────────────────────────────────────
$ConfigPath = Join-Path $PSScriptRoot '.env.local'

# Valores por defecto (sobrescritos por .env.local si existe)
$FTP_HOST    = 'ftpupload.net'
$FTP_USER    = 'if0_42133638'
$FTP_PASS    = ''                          # Rellenar en .env.local
$FTP_REMOTEDIR = '/htdocs'                 # Directorio raíz en el servidor

# Cargar .env.local si existe
if (Test-Path $ConfigPath) {
    Get-Content $ConfigPath | ForEach-Object {
        if ($_ -match '^\s*([^#][^=]*)=(.*)$') {
            $k = $Matches[1].Trim()
            $v = $Matches[2].Trim().Trim('"').Trim("'")
            switch ($k) {
                'FTP_HOST'      { $FTP_HOST      = $v }
                'FTP_USER'      { $FTP_USER      = $v }
                'FTP_PASS'      { $FTP_PASS      = $v }
                'FTP_REMOTEDIR' { $FTP_REMOTEDIR = $v }
            }
        }
    }
} else {
    Write-Warning "No se encontró .env.local. Crea el archivo con tus credenciales FTP."
    Write-Host @"
    
  Ejemplo de contenido para .env.local:
  ──────────────────────────────────────
  FTP_HOST=ftpupload.net
  FTP_USER=if0_42133638
  FTP_PASS=tu_contraseña_aqui
  FTP_REMOTEDIR=/htdocs

"@ -ForegroundColor Cyan
    exit 1
}

if ([string]::IsNullOrWhiteSpace($FTP_PASS)) {
    Write-Error "La contraseña FTP no está configurada en .env.local (FTP_PASS)."
    exit 1
}

# ──────────────────────────────────────────────────────────────
#  LISTA DE ARCHIVOS DEL PROYECTO (rutas relativas)
# ──────────────────────────────────────────────────────────────
$TodosLosArchivos = @(
    'index.php',
    'resultados.php',
    'api.php',
    'db_helper.php',
    'config.php',
    '.htaccess',
    'css/style.css',
    'js/app.js',
    'js/resultados.js',
    'images/asadito_colorado.jpg',
    'images/bistec_vallegrandino.png',
    'images/huminta.jpg',
    'images/asado_chancho.png'
)

# Nota: setup.php NO se incluye intencionalmente por razones de seguridad.
# Nota: data/, config.php y .gitignore tampoco se sincronizan automáticamente.

# ──────────────────────────────────────────────────────────────
#  SELECCIÓN DE ARCHIVOS A SUBIR
# ──────────────────────────────────────────────────────────────
$ArchivosASubir = @()

if ($Archivos.Count -gt 0) {
    # Modo parámetro directo
    $ArchivosASubir = $Archivos
} elseif ($Todo) {
    # Modo subida total
    $ArchivosASubir = $TodosLosArchivos
} else {
    # Modo interactivo: mostrar menú de selección
    Write-Host "`n🚀 upload_patch.ps1 — Sabores de Vallegrande`n" -ForegroundColor Cyan
    Write-Host "  Servidor : $FTP_HOST" -ForegroundColor DarkGray
    Write-Host "  Usuario  : $FTP_USER" -ForegroundColor DarkGray
    Write-Host "  Directorio remoto: $FTP_REMOTEDIR`n" -ForegroundColor DarkGray
    
    Write-Host "Archivos disponibles para subir:" -ForegroundColor Yellow
    for ($i = 0; $i -lt $TodosLosArchivos.Count; $i++) {
        Write-Host "  [$($i+1)] $($TodosLosArchivos[$i])"
    }
    Write-Host "  [A] Todos los archivos"
    Write-Host "  [Q] Cancelar y salir`n"
    
    $seleccion = Read-Host "Ingresa los números separados por coma (ej: 1,3,5) o [A] para todos"
    
    if ($seleccion -match '^[Qq]$') {
        Write-Host "Operación cancelada." -ForegroundColor Yellow
        exit 0
    } elseif ($seleccion -match '^[Aa]$') {
        $ArchivosASubir = $TodosLosArchivos
    } else {
        $indices = $seleccion -split ',' | ForEach-Object { $_.Trim() }
        foreach ($idx in $indices) {
            if ($idx -match '^\d+$') {
                $n = [int]$idx - 1
                if ($n -ge 0 -and $n -lt $TodosLosArchivos.Count) {
                    $ArchivosASubir += $TodosLosArchivos[$n]
                } else {
                    Write-Warning "Índice fuera de rango: $idx"
                }
            }
        }
    }
}

if ($ArchivosASubir.Count -eq 0) {
    Write-Warning "No se seleccionó ningún archivo para subir."
    exit 0
}

# ──────────────────────────────────────────────────────────────
#  FUNCIÓN DE SUBIDA FTP
# ──────────────────────────────────────────────────────────────
function Upload-FTP {
    param(
        [string]$LocalPath,
        [string]$RemotePath,
        [string]$Host,
        [string]$User,
        [string]$Pass
    )

    if (-not (Test-Path $LocalPath)) {
        Write-Warning "  ⚠️  Archivo local no encontrado: $LocalPath"
        return $false
    }

    try {
        $uri = "ftp://$Host$RemotePath"
        $request = [System.Net.FtpWebRequest]::Create($uri)
        $request.Method      = [System.Net.WebRequestMethods+Ftp]::UploadFile
        $request.Credentials = New-Object System.Net.NetworkCredential($User, $Pass)
        $request.UseBinary   = $true
        $request.UsePassive  = $true
        $request.KeepAlive   = $false

        $contenido = [System.IO.File]::ReadAllBytes($LocalPath)
        $request.ContentLength = $contenido.Length

        $stream = $request.GetRequestStream()
        $stream.Write($contenido, 0, $contenido.Length)
        $stream.Close()

        $response = $request.GetResponse()
        $estado   = $response.StatusDescription.Trim()
        $response.Close()

        Write-Host "  ✅  $RemotePath  →  $estado" -ForegroundColor Green
        return $true

    } catch {
        Write-Host "  ❌  Error subiendo $RemotePath : $_" -ForegroundColor Red
        return $false
    }
}

# ──────────────────────────────────────────────────────────────
#  PROCESO DE SUBIDA
# ──────────────────────────────────────────────────────────────
Write-Host "`n📤 Iniciando subida de $($ArchivosASubir.Count) archivo(s)...`n" -ForegroundColor Cyan

$exitosos = 0
$fallidos  = 0

foreach ($archivo in $ArchivosASubir) {
    # Construir ruta local
    $localPath  = Join-Path $PSScriptRoot $archivo

    # Construir ruta remota (con separadores Unix)
    $archivoUnix = $archivo -replace '\\', '/'
    $remotePath  = "$FTP_REMOTEDIR/$archivoUnix"

    # Crear directorios remotos intermedios si es necesario (no disponible en FTP básico,
    # InfinityFree ya tiene la estructura de carpetas creada desde el primer despliegue)
    Write-Host "  → Subiendo: $archivoUnix" -ForegroundColor DarkCyan

    $ok = Upload-FTP -LocalPath $localPath -RemotePath $remotePath `
                     -Host $FTP_HOST -User $FTP_USER -Pass $FTP_PASS

    if ($ok) { $exitosos++ } else { $fallidos++ }
}

# ──────────────────────────────────────────────────────────────
#  RESUMEN FINAL
# ──────────────────────────────────────────────────────────────
Write-Host "`n──────────────────────────────────────────" -ForegroundColor DarkGray
Write-Host "  Subidos exitosamente : $exitosos" -ForegroundColor Green
if ($fallidos -gt 0) {
    Write-Host "  Fallidos              : $fallidos" -ForegroundColor Red
}
Write-Host "──────────────────────────────────────────`n" -ForegroundColor DarkGray

if ($fallidos -eq 0) {
    Write-Host "🎉 ¡Despliegue completado con éxito!" -ForegroundColor Green
} else {
    Write-Host "⚠️  Despliegue completado con $fallidos error(es). Revisa los archivos fallidos." -ForegroundColor Yellow
}
