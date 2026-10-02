# Dot-source from the repository root to use the ignored tools prepared in this workspace.
$pairwiseRoot = Split-Path -Parent $PSScriptRoot
$pairwiseNode = Get-ChildItem -LiteralPath (Join-Path $pairwiseRoot '.tools') -Directory -Filter 'node-v*-win-x64' -ErrorAction SilentlyContinue | Sort-Object Name -Descending | Select-Object -First 1
if ($pairwiseNode) { $env:Path = $pairwiseNode.FullName + ';' + $env:Path }
$pairwiseIni = Join-Path $pairwiseRoot '.tools/php.ini'
if (Test-Path -LiteralPath $pairwiseIni) { $env:PHPRC = Split-Path -Parent $pairwiseIni }
$pairwiseBrowsers = Join-Path $pairwiseRoot '.tools/browsers'
if (Test-Path -LiteralPath $pairwiseBrowsers) { $env:PLAYWRIGHT_BROWSERS_PATH = $pairwiseBrowsers }
