# Spin up a parallel lab instance bound to a feature worktree.
#
#   ./local-dev/parallel/up-instance.ps1 -Name notifications -Index 1 -WorktreePath C:\...\laranode-wt-notifications
#
# Ports are derived from -Index so multiple instances never clash:
#   HTTP 8090+i  HTTPS 8440+i  REVERB 8100+i  VITE 5180+i  MYSQL 33060+i
# Container name: laranode-lab-<Name>   Project: laranode-<Name>
# Run tests with: docker exec laranode-lab-<Name> bash -lc 'cd /home/laranode_ln/panel && php artisan test'
param(
  [Parameter(Mandatory)][string]$Name,
  [Parameter(Mandatory)][int]$Index,
  [string]$WorktreePath = (Get-Location).Path
)
$ErrorActionPreference = 'Stop'

$compose = Join-Path $WorktreePath 'local-dev\parallel\docker-compose.parallel.yml'
if (-not (Test-Path $compose)) { throw "compose not found at $compose (is -WorktreePath a Laranode worktree?)" }

$env:INSTANCE_NAME = "laranode-lab-$Name"
$env:HTTP_PORT   = 8090 + $Index
$env:HTTPS_PORT  = 8440 + $Index
$env:REVERB_PORT = 8100 + $Index
$env:VITE_PORT   = 5180 + $Index
$env:MYSQL_PORT  = 33060 + $Index
$project = "laranode-$Name"

Write-Host "[$Name] starting project '$project' from $WorktreePath (HTTP :$($env:HTTP_PORT)) ..."
docker compose -p $project -f $compose up -d
if ($LASTEXITCODE -ne 0) { throw "compose up failed for $Name" }

Write-Host "[$Name] provisioning (composer/npm/migrate/mysql/postgres/seed) — first boot is slow ..."
docker exec "laranode-lab-$Name" bash -lc '/home/laranode_ln/panel/local-dev/entrypoint-setup.sh'
if ($LASTEXITCODE -ne 0) { throw "provision failed for $Name" }

Write-Host "[$Name] READY. Panel http://localhost:$($env:HTTP_PORT)"
Write-Host "[$Name] test: docker exec laranode-lab-$Name bash -lc 'cd /home/laranode_ln/panel && php artisan test'"
