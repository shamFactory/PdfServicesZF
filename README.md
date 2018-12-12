
# Pdf Services ZF2
This project is from Zend Framework 2 and is used to separate several sheets of a report PDF in different PDF files in a Zip. For example if you have 40 pages in a PDF with this class you could have 4 PDFs of 10 sheets in a Zip (including the paging and order of the PDFs).

## How to use
```
    $pdf =  new PdfService($this->getServiceLocator()); //you have to set the instance

    $pdf->setOptions([
        'MAX_ROW_FIRST_PAGE' => 10,
        'MAX_ROW_PAGE' => 10,
    ]);

    //set the view for the content
    $pdf->templateBody = 'application/pdf/row.phtml', 
    
    //set the data from your model
    $pdf->setData($myDataReport);

    //always inside on Do-While
    do {
        $generate = $pdf->generate('stock-cliente', new \Dompdf());//name PDF and instance DomPDF
    } while ($generate == 1);
```

And put in your view ('application/pdf/row.phtml')
```
    <div>
        <div><?php echo $row['MY_DATA_VAR_1'] ?></div>
        <div><?php echo $row['MY_DATA_VAR_2'] ?></div>
    </div>
```
Finally this show a PDF (if is only one PDF) or download a Zip (if there are two o more PDFs).

### Another options 
You can set header for the first page (before the data), footer for the last page (after the last data) and watermark (for all page). 
Example
```
    $pdf->setWatermark('application/pdf/watermark.phtml', [
        'my_custom_var' => 'my content var', 
        'my_custom_var_2' => 'my content var 2', 
    ]);

```
  
And put in your view ('application/pdf/watermark.phtml')
```
    <h1><?php echo $row['my_custom_var'] ?></h1>
    <h2><?php echo $row['my_custom_var_2'] ?></h2>
```