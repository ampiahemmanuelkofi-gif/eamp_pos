<?php
// Clear any previous output
if (ob_get_level())
    ob_end_clean();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=product_import_template.csv');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, array('Code', 'Name', 'Selling Price', 'Cost Price', 'Quantity', 'Category', 'Barcode', 'Supplier', 'Expiry', 'Batch No', 'Box Qty', 'Items/Box', 'Box Price', 'Box Wholesale'));

// Output a sample row (optional explanation in user interface, but helpful here)
// fputcsv($output, array('P001', 'Sample Product', '10.00', '5.00', '100', 'General', '123456789'));

fclose($output);
exit();
?>