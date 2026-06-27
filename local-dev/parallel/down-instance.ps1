# Tear down a parallel lab instance.
#   ./local-dev/parallel/down-instance.ps1 -Name notifications            # stop + remove container/network
#   ./local-dev/parallel/down-instance.ps1 -Name notifications -Nuke      # also remove the per-instance volumes
param(
  [Parameter(Mandatory)][string]$Name,
  [switch]$Nuke,
  [string]$WorktreePath = (Get-Location).Path
)
$ErrorActionPreference = 'Stop'

$compose = Join-Path $WorktreePath 'local-dev\parallel\docker-compose.parallel.yml'
$project = "laranode-$Name"

if ($Nuke) {
  Write-Host "[$Name] down + remove volumes (project $project) ..."
  docker compose -p $project -f $compose down -v
} else {
  Write-Host "[$Name] down (project $project) ..."
  docker compose -p $project -f $compose down
}
