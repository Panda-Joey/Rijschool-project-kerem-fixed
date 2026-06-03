$ErrorActionPreference = 'Stop'

$src = Join-Path $PSScriptRoot '..\src\style.css'
$outDir = Join-Path $PSScriptRoot '..\src\css\sharan'

New-Item -ItemType Directory -Force -Path $outDir | Out-Null

$text = Get-Content -Raw -Encoding UTF8 $src
$lines = $text -split "`r?`n"

# Section headers start with: /* ================================================================
$starts = New-Object System.Collections.Generic.List[int]
for ($i = 0; $i -lt $lines.Length; $i++) {
    if ($lines[$i] -match '^/\* ={10,}') {
        $starts.Add($i)
    }
}

# First header is the file header; skip it
$sectionStarts = @($starts | Where-Object { $_ -ne 0 })
$sectionStarts += $lines.Length

if ($sectionStarts.Count -lt 22) {
    throw "Not enough section headers found. Found: $($sectionStarts.Count)"
}

$files = @(
    @{ name = '01-base.css'; start = $sectionStarts[0]; end = $sectionStarts[3] }
    @{ name = '02-navigation.css'; start = $sectionStarts[3]; end = $sectionStarts[4] }
    @{ name = '03-messages.css'; start = $sectionStarts[4]; end = $sectionStarts[5] }
    @{ name = '04-forms.css'; start = $sectionStarts[5]; end = $sectionStarts[6] }
    @{ name = '05-buttons.css'; start = $sectionStarts[6]; end = $sectionStarts[7] }
    @{ name = '06-login.css'; start = $sectionStarts[7]; end = $sectionStarts[8] }
    @{ name = '07-dashboard-header-stats.css'; start = $sectionStarts[8]; end = $sectionStarts[9] }
    @{ name = '08-dashboard-next-lesson-month-nav.css'; start = $sectionStarts[9]; end = $sectionStarts[10] }
    @{ name = '09-dashboard-lessen.css'; start = $sectionStarts[10]; end = $sectionStarts[11] }
    @{ name = '10-calendar.css'; start = $sectionStarts[11]; end = $sectionStarts[12] }
    @{ name = '11-upcoming-lessons.css'; start = $sectionStarts[12]; end = $sectionStarts[13] }
    @{ name = '12-wijzig.css'; start = $sectionStarts[13]; end = $sectionStarts[14] }
    @{ name = '13-annuleer.css'; start = $sectionStarts[14]; end = $sectionStarts[15] }
    @{ name = '14-beschikbaarheid.css'; start = $sectionStarts[15]; end = $sectionStarts[16] }
    @{ name = '15-les-inroosteren.css'; start = $sectionStarts[16]; end = $sectionStarts[17] }
    @{ name = '16-modal.css'; start = $sectionStarts[17]; end = $sectionStarts[18] }
    @{ name = '17-responsive-tablet.css'; start = $sectionStarts[18]; end = $sectionStarts[19] }
    @{ name = '18-responsive-desktop.css'; start = $sectionStarts[19]; end = $sectionStarts[20] }
    @{ name = '19-extras.css'; start = $sectionStarts[20]; end = $sectionStarts[21] }
)

foreach ($f in $files) {
    $chunk = $lines[$f.start..($f.end - 1)] -join "`r`n"
    Set-Content -Encoding UTF8 -Path (Join-Path $outDir $f.name) -Value $chunk
}

$imports = @(
    '/* Sharan CSS split: keep same order */'
    '@import url("css/sharan/01-base.css");'
    '@import url("css/sharan/02-navigation.css");'
    '@import url("css/sharan/03-messages.css");'
    '@import url("css/sharan/04-forms.css");'
    '@import url("css/sharan/05-buttons.css");'
    '@import url("css/sharan/06-login.css");'
    '@import url("css/sharan/07-dashboard-header-stats.css");'
    '@import url("css/sharan/08-dashboard-next-lesson-month-nav.css");'
    '@import url("css/sharan/09-dashboard-lessen.css");'
    '@import url("css/sharan/10-calendar.css");'
    '@import url("css/sharan/11-upcoming-lessons.css");'
    '@import url("css/sharan/12-wijzig.css");'
    '@import url("css/sharan/13-annuleer.css");'
    '@import url("css/sharan/14-beschikbaarheid.css");'
    '@import url("css/sharan/15-les-inroosteren.css");'
    '@import url("css/sharan/16-modal.css");'
    '@import url("css/sharan/17-responsive-tablet.css");'
    '@import url("css/sharan/18-responsive-desktop.css");'
    '@import url("css/sharan/19-extras.css");'
) -join "`r`n"

Set-Content -Encoding UTF8 -Path $src -Value $imports

Write-Host "Done. Wrote $($files.Count) files to $outDir and replaced src/style.css with imports."

