# Pakuje folder do zipa z uprawnieniami jak na serwerze Linux: foldery 755, pliki 644, skrypty .sh 755.
# Zwykly zip z Windows rozpakowuje sie w DirectAdminie z uprawnieniami 777/666 i strona daje blad 403,
# dopoki cron ich nie poprawi. Tu kazdy wpis dostaje uprawnienia z listy (format mtree programu tar).
# Uzycie: powershell -NoProfile -ExecutionPolicy Bypass -File zip-linux.ps1 -Root C:\robocze -Folder typer -Out C:\cel\typer-kod.zip
# Kod wyjscia inny niz 0: wywolujacy robi zwykly zip (cron poprawi uprawnienia jak dotad).
param(
    [Parameter(Mandatory = $true)][string]$Root,
    [Parameter(Mandatory = $true)][string]$Folder,
    [Parameter(Mandatory = $true)][string]$Out,
    # Pliki w zipie bez folderu nadrzednego: rozpakowanie w folderze strony na serwerze kladzie je od razu na miejsce.
    [switch]$BezFolderu
)

$ErrorActionPreference = 'Stop'

function Escape-Mtree([string]$text) {
    # Szybka droga: zwykle nazwy (bez spacji i znakow specjalnych) zostaja bez zmian. Przy tysiacach plikow w vendor
    # zamiana znak po znaku trwala kilka minut i wygladalo, jakby paczka stanela na kroku 4/5.
    if ($text -cnotmatch '[^\x21-\x7e]|[\\#=]') { return $text }
    $builder = New-Object System.Text.StringBuilder
    foreach ($char in $text.ToCharArray()) {
        $code = [int]$char
        if ($code -le 32 -or $code -ge 127 -or $char -eq '\' -or $char -eq '#' -or $char -eq '=') {
            [void]$builder.Append('\' + [Convert]::ToString($code, 8).PadLeft(3, '0'))
        } else {
            [void]$builder.Append($char)
        }
    }
    return $builder.ToString()
}

try {
    $base = (Resolve-Path -LiteralPath (Join-Path $Root $Folder)).Path.TrimEnd('\')
    $items = Get-ChildItem -LiteralPath $base -Recurse -Force | Where-Object { -not ($_.Attributes -band [IO.FileAttributes]::ReparsePoint) }

    # Polskie litery w nazwach plikow tar w Windows czyta w innym kodowaniu: wtedy bezpieczniej zwykly zip.
    if ($items | Where-Object { $_.FullName -match '[^\x20-\x7e]' } | Select-Object -First 1) {
        Write-Host 'W nazwach plikow sa znaki spoza ASCII - robie zwykly zip.'
        exit 2
    }

    $lines = New-Object System.Collections.Generic.List[string]
    $lines.Add('#mtree')
    $prefix = $Folder + '/'
    if ($BezFolderu) { $prefix = '' } else { $lines.Add((Escape-Mtree $Folder) + '/ type=dir mode=0755') }

    foreach ($item in $items) {
        $relative = $prefix + $item.FullName.Substring($base.Length + 1).Replace('\', '/')
        if ($item.PSIsContainer) {
            $lines.Add((Escape-Mtree $relative) + ' type=dir mode=0755')
        } else {
            $mode = '0644'
            if ($item.Extension -eq '.sh') { $mode = '0755' }
            $lines.Add((Escape-Mtree $relative) + ' type=file mode=' + $mode + ' time=' + [DateTimeOffset]::new($item.LastWriteTimeUtc).ToUnixTimeSeconds() + '.0 contents=' + (Escape-Mtree $item.FullName.Replace('\', '/')))
        }
    }

    $spec = Join-Path $env:TEMP ('typer-paczka-' + [Guid]::NewGuid().ToString('N') + '.mtree')
    [IO.File]::WriteAllLines($spec, $lines, (New-Object System.Text.UTF8Encoding $false))

    & tar -a -c -f $Out "@$spec"
    $code = $LASTEXITCODE
    Remove-Item -LiteralPath $spec -ErrorAction SilentlyContinue

    if ($code -ne 0) {
        Write-Host 'tar nie przyjal listy plikow - robie zwykly zip.'
        Remove-Item -LiteralPath $Out -ErrorAction SilentlyContinue
        exit 3
    }

    Write-Host ('Spakowano ' + $items.Count + ' plikow i folderow z uprawnieniami dla serwera (755/644).')
    exit 0
} catch {
    Write-Host ('Pakowanie z uprawnieniami nie wyszlo: ' + $_.Exception.Message + ' - robie zwykly zip.')
    exit 4
}
