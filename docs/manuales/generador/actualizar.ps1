param([string]$Docx, [string]$Pdf)
# Abre el .docx en Word, actualiza el índice y los campos, lo guarda y exporta a PDF.
$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0
try {
  $doc = $word.Documents.Open($Docx, $false, $false)
  foreach ($toc in $doc.TablesOfContents) { $toc.Update() }
  $doc.Fields.Update() | Out-Null
  foreach ($toc in $doc.TablesOfContents) { $toc.UpdatePageNumbers() }
  $doc.Save()
  $doc.SaveAs2([ref]$Pdf, [ref]17)
  $doc.Close([ref]0)
} finally { $word.Quit() }
