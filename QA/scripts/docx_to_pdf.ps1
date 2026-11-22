# Konversi reports/Laporan_QA_SiLaundry.docx -> PDF memakai Microsoft Word (memperbarui daftar isi & nomor halaman).
#   powershell -ExecutionPolicy Bypass -File scripts/docx_to_pdf.ps1
param(
    [string]$Docx = (Join-Path $PSScriptRoot '..\reports\Laporan_QA_SiLaundry.docx')
)
Add-Type -AssemblyName Microsoft.VisualBasic
$VB = [Microsoft.VisualBasic.Interaction]
$Get = [Microsoft.VisualBasic.CallType]::Get
$Call = [Microsoft.VisualBasic.CallType]::Method

$docxPath = (Resolve-Path $Docx).Path
$pdfPath = [System.IO.Path]::ChangeExtension($docxPath, '.pdf')

$word = New-Object -ComObject Word.Application
try {
    $VB::CallByName($word, 'Visible', [Microsoft.VisualBasic.CallType]::Let, $false) | Out-Null
    $docs = $VB::CallByName($word, 'Documents', $Get)
    $doc = $VB::CallByName($docs, 'Open', $Call, $docxPath)
    $tocs = $VB::CallByName($doc, 'TablesOfContents', $Get)
    if ($VB::CallByName($tocs, 'Count', $Get) -gt 0) {
        $toc = $VB::CallByName($tocs, 'Item', $Call, 1)
        $VB::CallByName($toc, 'Update', $Call) | Out-Null
    }
    $fields = $VB::CallByName($doc, 'Fields', $Get)
    $VB::CallByName($fields, 'Update', $Call) | Out-Null
    $VB::CallByName($doc, 'Save', $Call) | Out-Null
    $VB::CallByName($doc, 'ExportAsFixedFormat', $Call, $pdfPath, 17) | Out-Null
    $VB::CallByName($doc, 'Close', $Call, 0) | Out-Null
    Write-Output "PDF: $pdfPath"
}
finally {
    $VB::CallByName($word, 'Quit', $Call) | Out-Null
}
