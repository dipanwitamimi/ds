<?php
class Functions {
    private $db;
    
    public function __construct($database) {
        $this->db = $database->getConnection();
    }
    
    // Format date to YYYY-MM-DD
    public function formatDate($date) {
        return date('Y-m-d', strtotime($date));
    }
    
    // Format datetime to YYYY-MM-DD HH:MM:SS
    public function formatDateTime($datetime) {
        return date('Y-m-d H:i:s', strtotime($datetime));
    }
    
    // Generate Excel File
    public function generateExcel($data, $filename) {
        require_once ROOT_PATH . 'vendor/autoload.php';
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Add headers
        $col = 1;
        foreach(array_keys($data[0]) as $header) {
            $sheet->setCellValueByColumnAndRow($col, 1, ucwords(str_replace('_', ' ', $header)));
            $col++;
        }
        
        // Add data
        $row = 2;
        foreach($data as $record) {
            $col = 1;
            foreach($record as $value) {
                $sheet->setCellValueByColumnAndRow($col, $row, $value);
                $col++;
            }
            $row++;
        }
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }
    
    // Get current balance for a bank account
    public function getBankBalance($accountId) {
        try {
            $stmt = $this->db->prepare("SELECT current_balance FROM bank_accounts WHERE id = :id");
            $stmt->bindParam(":id", $accountId);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['current_balance'] : 0;
        } catch(PDOException $e) {
            return 0;
        }
    }
    
    // Update bank balance
    public function updateBankBalance($accountId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE bank_accounts ba
                SET current_balance = (
                    ba.opening_balance + 
                    COALESCE((SELECT SUM(credit) FROM transactions WHERE bank_account_id = ba.id), 0) -
                    COALESCE((SELECT SUM(debit) FROM transactions WHERE bank_account_id = ba.id), 0)
                )
                WHERE ba.id = :id
            ");
            $stmt->bindParam(":id", $accountId);
            return $stmt->execute();
        } catch(PDOException $e) {
            return false;
        }
    }
    
    // Get closing stock/balance from previous date
    public function getPreviousClosing($table, $staffId, $date, $field = 'closing_stock') {
        try {
            $stmt = $this->db->prepare("
                SELECT $field 
                FROM $table 
                WHERE staff_id = :staff_id 
                AND transaction_date < :date 
                ORDER BY transaction_date DESC 
                LIMIT 1
            ");
            $stmt->bindParam(":staff_id", $staffId);
            $stmt->bindParam(":date", $date);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result[$field] : 0;
        } catch(PDOException $e) {
            return 0;
        }
    }
    
    // Calculate denomination total
    public function calculateDenominationTotal($data) {
        return ($data['notes_2000'] * 2000) +
               ($data['notes_500'] * 500) +
               ($data['notes_200'] * 200) +
               ($data['notes_100'] * 100) +
               ($data['notes_50'] * 50) +
               ($data['notes_20'] * 20) +
               ($data['notes_10'] * 10) +
               ($data['notes_5'] * 5) +
               ($data['notes_2'] * 2) +
               ($data['notes_1'] * 1);
    }
}