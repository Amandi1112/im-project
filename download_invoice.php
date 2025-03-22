<?php
// This file handles the download of invoice PDFs

// Check if file parameter exists
if (!isset($_GET['file'])) {
    die('No file specified');
}

$file = $_GET['file'];

// Security check - only allow PDF files from the invoices directory
$file = basename($file); // Remove any directory components
$path = 'invoices/' . $file;

// Validate that the file exists and is a PDF
if (!file_exists($path) || pathinfo($path, PATHINFO_EXTENSION) != 'pdf') {
    die('Invalid file');
}

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));

// Output the file
readfile($path);
exit;
?>