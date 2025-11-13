param(
  [string[]]$Roots = @("frontend", "backend"),
  [int]$SourceCodePage = 1250
)

function Test-IsUtf8($bytes){
  try{
    $enc = New-Object System.Text.UTF8Encoding($false, $true)
    [void]$enc.GetString($bytes)
    return $true
  }catch{ return $false }
}

$srcEnc = [System.Text.Encoding]::GetEncoding($SourceCodePage)
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)

$patterns = @('*.vue','*.js','*.ts','*.php','*.json','*.md','.env')

foreach($root in $Roots){
  if(-not (Test-Path $root)){ continue }
  foreach($pat in $patterns){
    Get-ChildItem -Path $root -Recurse -File -Filter $pat | ForEach-Object {
      $path = $_.FullName
      try{
        $bytes = [System.IO.File]::ReadAllBytes($path)
        if(-not (Test-IsUtf8 $bytes)){
          $text = $srcEnc.GetString($bytes)
          [System.IO.File]::WriteAllText($path, $text, $utf8NoBom)
          Write-Output "Converted to UTF-8: $path"
        }
      }catch{
        Write-Warning ("Failed to process {0}: {1}" -f $path, $_)
      }
    }
  }
}

Write-Output "Done."
