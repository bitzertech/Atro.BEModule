# Copy.ps1
$ErrorActionPreference = 'Stop'

# Brug scriptets mappe hvis muligt, ellers current dir
$LOCAL = if ($PSScriptRoot) { $PSScriptRoot } else { (Get-Location).Path }
$LOCAL  = [System.IO.Path]::GetFullPath($LOCAL)
$LOCAL_UNIX = $LOCAL -replace '\\','/'

# Azure host
$HOSTUSER = "azureuser"
$HOSTNAME = "vmdockerwebdkatropim.westeurope.cloudapp.azure.com"
$REMOTE_ROOT = "/home/$HOSTUSER/dev/Atro.BEModule"
$REMOTE_SPEC = "${HOSTUSER}@${HOSTNAME}:${REMOTE_ROOT}/"

# Sanity: kør fra modulets rod
if (!(Test-Path "$LOCAL\app") -or !(Test-Path "$LOCAL\composer.json")) {
  throw "Kør Copy.ps1 fra modulets rod (skal indeholde 'app' og 'composer.json'). Aktuel: $LOCAL"
}

# 1) Opret remote mappe (uden quotes – ingen spaces i stien)
ssh "$HOSTUSER@$HOSTNAME" "mkdir -p $REMOTE_ROOT && test -d $REMOTE_ROOT && echo READY || echo FAIL"

# 2) Kopiér (uden quotes omkring remote-sti pga. Windows scp)
scp -r "$LOCAL_UNIX/app"            $REMOTE_SPEC
scp     "$LOCAL_UNIX/composer.json" $REMOTE_SPEC

# (valgfrit) apply.sh
if (Test-Path "$LOCAL\apply.sh") {
  scp "$LOCAL_UNIX/apply.sh" $REMOTE_SPEC
  ssh "$HOSTUSER@$HOSTNAME" "chmod +x $REMOTE_ROOT/apply.sh"
}

Write-Host "Kopieret fra $LOCAL til $REMOTE_SPEC" -ForegroundColor Green
