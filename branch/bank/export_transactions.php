<?php
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$currentDateTime = date('Y-m-d H:i:s');
$currentUser = 'sgpriyom';

$db = new Database();
$conn = $db->getConnection();

$accountId = $_GET['id'] ?? 0;

// Get account details
$stmt = $conn->prepare("SELECT * FROM bank_accounts WHERE id = ?");
$stmt->execute([$accountId]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if ($account) {
    // Get transactions
    $stmt = $conn->prepare("
        SELECT * FROM transactions 
        WHERE account_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$accountId]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create new spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Add header information
    $sheet->setCellValue('A1', 'Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): ' . $currentDateTime);
    $sheet->setCellValue('A2', 'Current User\'s Login: ' . $currentUser);
    $sheet->setCellValue('A3', '');

    // Add account information
    $sheet->setCellValue('A4', 'Bank Name: ' . $account['bank_name']);
    $sheet->setCellValue('A5', 'Account Number: ' . $account['account_number']);
    $sheet->setCellValue('A6', '');

    // Add column headers
    $sheet->setCellValue('A7', 'Date & Time');
    $sheet->setCellValue('B7', 'Description');
    $sheet->setCellValue('C7', 'Credit');
    $sheet->setCellValue('D7', 'Debit');
    $sheet->setCellValue('E7', 'Balance');
    $sheet->setCellValue('F7', 'Created By');

    // Add transactions
    $row = 8;
    $balance = $account['opening_balance'];
    foreach ($transactions as $trans) {
        $balance += ($trans['credit'] - $trans['debit']);

        $sheet->setCellValue('A' . $row, $trans['created_at']);
        $sheet->setCellValue('B' . $row, $trans['description']);
        $sheet->setCellValue('C' . $row, $trans['credit']);
        $sheet->setCellValue('D' . $row, $trans['debit']);
        $sheet->setCellValue('E' . $row, $balance);
        $sheet->setCellValue('F' . $row, $trans['created_by']);

        $row++;
    }

    // Auto-size columns
    foreach (range('A', 'F') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="bank_transactions_' . $accountId . '_' . date('Y-m-d') . '.xlsx"');
    header('Cache-Control: max-age=0');

    // Save file
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} else {
    echo "Account not found.";
}