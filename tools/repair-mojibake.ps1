param(
  [string[]]$Roots = @("frontend"),
  [string[]]$Patterns = @('*.vue','*.js','*.ts','*.json','*.md','*.html'),
  [switch]$DryRun
)

function Score-Text($text){
  $hunChars = @(
    225,233,237,243,246,337,250,252,369,  # á é í ó ö ő ú ü ű
    193,201,205,211,214,336,218,220,368   # Á É Í Ó Ö Ő Ú Ü Ű
  )
  $bad = [char]0xFFFD
  $hunCount = 0
  foreach($ch in $text.ToCharArray()){
    if($hunChars -contains [int][char]$ch){ $hunCount++ }
  }
  $badCount = ($text.ToCharArray() | Where-Object { $_ -eq $bad }).Count
  return @{ hun = $hunCount; bad = $badCount }
}

function Try-Repair($text){
  $enc1250 = [System.Text.Encoding]::GetEncoding(1250)
  $enc1252 = [System.Text.Encoding]::GetEncoding(1252)
  $utf8Strict = New-Object System.Text.UTF8Encoding($false,$true)
  $orig = Score-Text $text
  $best = @{ text = $text; score = $orig; note='orig' }

  foreach($enc in @($enc1250, $enc1252)){
    try{
      $bytes = $enc.GetBytes($text)
      $fixed = $utf8Strict.GetString($bytes)
      $sc = Score-Text $fixed
      if(($sc.hun -gt $best.score.hun) -or (($sc.hun -eq $best.score.hun) -and ($sc.bad -lt $best.score.bad))){
        $best = @{ text = $fixed; score = $sc; note = "${($enc.BodyName)}->utf8" }
      }
    }catch{}
  }
  return $best
}

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$badChar = [char]0xFFFD

$rej = ([char]195).ToString() + '|' + ([char]194).ToString() + '|' + ([char]197).ToString()

foreach($root in $Roots){
  if(-not (Test-Path $root)){ continue }
  foreach($pat in $Patterns){
    Get-ChildItem -Path $root -Recurse -File -Filter $pat | ForEach-Object {
      $path = $_.FullName
      try{
        $text = Get-Content -Path $path -Raw -Encoding UTF8
        if($text.IndexOf($badChar) -lt 0 -and ($text -notmatch $rej)) { return }
        $res = Try-Repair $text
        if($res.score.hun -gt (Score-Text $text).hun -or $res.score.bad -lt (Score-Text $text).bad){
          if($DryRun){ Write-Output ("Would repair: {0} via {1} (hun:{2}->{3}, bad:{4}->{5})" -f $path, $res.note, (Score-Text $text).hun, $res.score.hun, (Score-Text $text).bad, $res.score.bad) }
          else {
            Copy-Item -Path $path -Destination "$path.bak" -Force
            [System.IO.File]::WriteAllText($path, $res.text, $utf8NoBom)
            Write-Output ("Repaired: {0} via {1} (hun:{2}->{3}, bad:{4}->{5})" -f $path, $res.note, (Score-Text $text).hun, $res.score.hun, (Score-Text $text).bad, $res.score.bad)
          }
        }
      }catch{ Write-Warning ("Failed: {0} => {1}" -f $path, $_) }
    }
  }
}

Write-Output "Done."
