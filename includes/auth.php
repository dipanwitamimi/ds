<?php
class Auth {
    private $db;
    
    public function __construct($database) {
        $this->db = $database->getConnection();
    }
    
    public function login($username, $password, $userType) {
        try {
            $table = ($userType === 'admin') ? 'distributors' : 'branches';
            
            $stmt = $this->db->prepare("SELECT * FROM {$table} WHERE username = :username");
            $stmt->bindParam(":username", $username);
            $stmt->execute();
            
            if($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if(password_verify($password, $user['password'])) {
                    // Set session data
                    Session::setUser([
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'type' => $userType,
                        'branch_id' => ($userType === 'branch') ? $user['id'] : null
                    ]);
                    
                    // Log the login
                    $this->logUserActivity($user['id'], $userType, 'login');
                    
                    return true;
                }
            }
            return false;
        } catch(PDOException $e) {
            return false;
        }
    }
    
    private function logUserActivity($userId, $userType, $action) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_activity_log 
                (user_id, user_type, action, ip_address, datetime)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $userType,
                $action,
                $_SERVER['REMOTE_ADDR'],
                Session::getCurrentDateTime()
            ]);
        } catch(PDOException $e) {
            // Log error silently
        }
    }
    
    public function logout() {
        $user = Session::getUser();
        if ($user) {
            $this->logUserActivity($user['id'], $user['type'], 'logout');
        }
        Session::logout();
        return true;
    }
}


function checkBranchAuth() {
    if (!isset($_SESSION['branch_user'])) {
        $_SESSION['error'] = "Please login to continue";
        header('Location: ../../login.php'); // Adjust the path to your login page
        exit;
    }
}