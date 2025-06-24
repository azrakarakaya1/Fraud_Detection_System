<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Initialize fraud detector
$fraudDetector = null;
try {
    require_once 'config/database.php';
    if (file_exists('fraud_detection.php')) {
        require_once 'fraud_detection.php';
        $fraudDetector = new FraudDetector(getDatabaseConnection());
    }
} catch (Exception $e) {
    error_log("Could not initialize fraud detector: " . $e->getMessage());
}

try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

function getEndpoint() {
    if (isset($_SERVER['PATH_INFO'])) {
        $pathInfo = trim($_SERVER['PATH_INFO'], '/');
        $segments = $pathInfo ? explode('/', $pathInfo) : [];
        return [$segments[0] ?? '', $segments];
    }
    
    $request_uri = $_SERVER['REQUEST_URI'];
    $script_name = $_SERVER['SCRIPT_NAME'];
    
    if (strpos($request_uri, '?') !== false) {
        $request_uri = substr($request_uri, 0, strpos($request_uri, '?'));
    }
    
    $path_info = str_replace($script_name, '', $request_uri);
    $path_info = trim($path_info, '/');
    
    if ($path_info) {
        $segments = explode('/', $path_info);
        return [$segments[0], $segments];
    }
    
    if (isset($_GET['endpoint'])) {
        return [$_GET['endpoint'], [$_GET['endpoint']]];
    }
    
    return ['', []];
}

list($endpoint, $segments) = getEndpoint();
$method = $_SERVER['REQUEST_METHOD'];

switch ($endpoint) {
    case 'users':
        handleUsers($pdo, $method);
        break;
    case 'transactions':
        handleTransactions($pdo, $method, $fraudDetector);
        break;
    case 'profiles':
        handleProfiles($pdo, $method);
        break;
    case 'dashboard':
        handleDashboard($pdo, $method);
        break;
    case 'alerts':
        handleAlerts($pdo, $method, $segments);
        break;
    case 'test':
        handleTest($pdo);
        break;
    case '':
        echo json_encode([
            'success' => true,
            'message' => 'Fraud Detection API is running',
            'time' => date('Y-m-d H:i:s')
        ]);
        break;
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Endpoint not found: ' . $endpoint]);
}

function handleTest($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM User1");
        $userCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM Transaction");
        $transactionCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        echo json_encode([
            'success' => true,
            'message' => 'API working correctly',
            'data' => [
                'users_count' => $userCount,
                'transactions_count' => $transactionCount,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Test failed: ' . $e->getMessage()]);
    }
}

function handleUsers($pdo, $method) {
    if ($method === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            $data = $_POST;
        }
        
        if (!isset($data['userID']) || !isset($data['userName']) || !isset($data['userEmail'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            return;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT UserID FROM User1 WHERE UserID = ? OR Email = ?");
            $stmt->execute([$data['userID'], $data['userEmail']]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'error' => 'User ID or Email already exists']);
                return;
            }
            
            $password = $data['userPassword'] ?? 'defaultpass';
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO User1 (UserID, Name, Email, Phone, Password, RegistrationDate) 
                VALUES (?, ?, ?, ?, ?, CURDATE())
            ");
            
            $result = $stmt->execute([
                $data['userID'],
                $data['userName'],
                $data['userEmail'],
                $data['userPhone'] ?? '',
                $hashedPassword
            ]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'data' => ['message' => 'User created successfully', 'userID' => $data['userID']]
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to create user']);
            }
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
        
    } elseif ($method === 'GET') {
        try {
            $stmt = $pdo->query("SELECT UserID, Name, Email, Phone, RegistrationDate FROM User1 ORDER BY RegistrationDate DESC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $users]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Failed to fetch users']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
}

function handleTransactions($pdo, $method, $fraudDetector) {
    if ($method === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            $data = $_POST;
        }
        
        $required = ['transactionID', 'transactionUserID', 'amount'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
                return;
            }
        }
        
        try {
            $stmt = $pdo->prepare("SELECT UserID FROM User1 WHERE UserID = ?");
            $stmt->execute([$data['transactionUserID']]);
            if ($stmt->rowCount() === 0) {
                echo json_encode(['success' => false, 'error' => 'User not found']);
                return;
            }
            
            $stmt = $pdo->prepare("SELECT TransactionID FROM Transaction WHERE TransactionID = ?");
            $stmt->execute([$data['transactionID']]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'error' => 'Transaction ID already exists']);
                return;
            }
            
            if (strlen($data['transactionID']) > 10) {
                echo json_encode(['success' => false, 'error' => 'Transaction ID too long']);
                return;
            }
            
            $date = $data['date'] ?? date('Y-m-d');
            $time = $data['time'] ?? date('H:i:s');
            $location = $data['location'] ?? 'Unknown';
            $paymentMethod = $data['paymentMethod'] ?? 'Unknown';
            $categoryID = $data['categoryID'] ?? 'C001';
            $amount = floatval($data['amount']);
            
            // Insert transaction first
            $stmt = $pdo->prepare("
                INSERT INTO Transaction (TransactionID, UserID, CategoryID, Amount, Date, Time, Location, PaymentMethod) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['transactionID'],
                $data['transactionUserID'],
                $categoryID,
                $amount,
                $date,
                $time,
                $location,
                $paymentMethod
            ]);
            
            if ($result) {
                $responseData = [
                    'message' => 'Transaction created successfully',
                    'transactionID' => $data['transactionID']
                ];
                
                // Check if transaction is suspicious and create alert
                $alertCreated = false;
                
                // Use fraud detection system if available
                if ($fraudDetector) {
                    $fraudAnalysis = $fraudDetector->analyzeTransaction($data);
                    $responseData['fraud_analysis'] = $fraudAnalysis;
                    
                    if ($fraudAnalysis['alert_created']) {
                        $alertCreated = true;
                        $responseData['alert_created'] = true;
                        $responseData['alert_message'] = 'Advanced fraud detection alert created';
                    }
                }
                
                // Simple fallback alert creation for high amounts
                if (!$alertCreated && $amount >= 10000) {
                    $alertID = createSuspiciousTransactionAlert($pdo, $data['transactionID'], $data['transactionUserID'], $amount);
                    if ($alertID) {
                        $responseData['alert_created'] = true;
                        $responseData['alert_id'] = $alertID;
                        $responseData['alert_message'] = 'High amount alert created';
                        $alertCreated = true;
                    }
                }
                
                // Log the result
                if ($alertCreated) {
                    error_log("🚨 ALERT CREATED for transaction {$data['transactionID']} by user {$data['transactionUserID']} amount: $amount");
                }
                
                echo json_encode(['success' => true, 'data' => $responseData]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to create transaction']);
            }
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Transaction error: ' . $e->getMessage()]);
        }
        
    } elseif ($method === 'GET') {
        try {
            $stmt = $pdo->query("
                SELECT t.*, u.Name as UserName 
                FROM Transaction t 
                LEFT JOIN User1 u ON t.UserID = u.UserID 
                ORDER BY t.Date DESC, t.Time DESC 
                LIMIT 50
            ");
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $transactions]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Failed to fetch transactions']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
}

// NEW FUNCTION: Create alert for suspicious transactions
function createSuspiciousTransactionAlert($pdo, $transactionID, $userID, $amount) {
    try {
        // Generate unique IDs
        $timestamp = time();
        $random = rand(100, 999);
        $alertID = 'AL' . substr($timestamp, -6) . $random;
        $anomalyID = 'AN' . substr($timestamp, -6) . $random;
        
        // Make sure AlertID is unique
        $checkStmt = $pdo->prepare("SELECT AlertID FROM Alert WHERE AlertID = ?");
        $checkStmt->execute([$alertID]);
        
        while ($checkStmt->rowCount() > 0) {
            $random = rand(100, 999);
            $alertID = 'AL' . substr($timestamp, -6) . $random;
            $checkStmt->execute([$alertID]);
        }
        
        // Make sure AnomalyID is unique
        $checkStmt = $pdo->prepare("SELECT AnomalyID FROM AnomalyRecord WHERE AnomalyID = ?");
        $checkStmt->execute([$anomalyID]);
        
        while ($checkStmt->rowCount() > 0) {
            $random = rand(100, 999);
            $anomalyID = 'AN' . substr($timestamp, -6) . $random;
            $checkStmt->execute([$anomalyID]);
        }
        
        // Create AnomalyRecord table if needed
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS AnomalyRecord (
                AnomalyID VARCHAR(20) PRIMARY KEY,
                TransactionID VARCHAR(20) NOT NULL,
                UserID VARCHAR(20) NOT NULL,
                AnomalyType VARCHAR(50) NOT NULL,
                DetectedDate DATE NOT NULL,
                Status VARCHAR(50) DEFAULT 'pending_review'
            )
        ");
        
        // Determine anomaly type based on amount
        if ($amount >= 30000) {
            $anomalyType = 'critical_amount';
        } elseif ($amount >= 20000) {
            $anomalyType = 'very_high_amount';
        } elseif ($amount >= 15000) {
            $anomalyType = 'high_amount';
        } else {
            $anomalyType = 'suspicious_amount';
        }
        
        // Step 1: Create anomaly record first
        $stmt = $pdo->prepare("
            INSERT INTO AnomalyRecord (AnomalyID, TransactionID, UserID, AnomalyType, DetectedDate, Status) 
            VALUES (?, ?, ?, ?, CURDATE(), 'pending_review')
        ");
        
        $anomalyResult = $stmt->execute([
            $anomalyID,
            $transactionID,
            $userID,
            $anomalyType
        ]);
        
        if (!$anomalyResult) {
            error_log("❌ Failed to create anomaly record for transaction $transactionID");
            return false;
        }
        
        // Step 2: Create alert record
        $stmt = $pdo->prepare("
            INSERT INTO Alert (AlertID, AnomalyID, UserID, Timestamp) 
            VALUES (?, ?, ?, NOW())
        ");
        
        $alertResult = $stmt->execute([
            $alertID,
            $anomalyID,
            $userID
        ]);
        
        if ($alertResult) {
            error_log("✅ SUCCESS: Alert created! AlertID=$alertID, UserID=$userID, Amount=$amount, AnomalyType=$anomalyType");
            return $alertID;
        } else {
            error_log("❌ Failed to create alert for transaction $transactionID");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("❌ Error creating alert: " . $e->getMessage());
        return false;
    }
}

function handleProfiles($pdo, $method) {
    if ($method === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            $data = $_POST;
        }
        
        if (!isset($data['profileUserID'])) {
            echo json_encode(['success' => false, 'error' => 'Missing user ID']);
            return;
        }
        
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS Profile (
                    UserID VARCHAR(50) NOT NULL PRIMARY KEY,
                    AvgMonthlySpend DECIMAL(15,2) DEFAULT 0.00,
                    CategoryLimits TEXT,
                    TimeWindows VARCHAR(100) DEFAULT '09:00-22:00',
                    FrequentLocations TEXT
                )
            ");
            
            $stmt = $pdo->prepare("SELECT UserID FROM Profile WHERE UserID = ?");
            $stmt->execute([$data['profileUserID']]);
            
            if ($stmt->rowCount() > 0) {
                $updateStmt = $pdo->prepare("
                    UPDATE Profile 
                    SET AvgMonthlySpend = ?, CategoryLimits = ?, TimeWindows = ?, FrequentLocations = ?
                    WHERE UserID = ?
                ");
                $result = $updateStmt->execute([
                    $data['avgMonthlySpend'] ?? 0,
                    $data['categoryLimits'] ?? '{}',
                    $data['timeWindows'] ?? '09:00-22:00',
                    $data['frequentLocations'] ?? '',
                    $data['profileUserID']
                ]);
            } else {
                $insertStmt = $pdo->prepare("
                    INSERT INTO Profile (UserID, AvgMonthlySpend, CategoryLimits, TimeWindows, FrequentLocations)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $result = $insertStmt->execute([
                    $data['profileUserID'],
                    $data['avgMonthlySpend'] ?? 0,
                    $data['categoryLimits'] ?? '{}',
                    $data['timeWindows'] ?? '09:00-22:00',
                    $data['frequentLocations'] ?? ''
                ]);
            }
            
            if ($result) {
                echo json_encode(['success' => true, 'data' => ['message' => 'Profile saved successfully']]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to save profile']);
            }
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Profile error: ' . $e->getMessage()]);
        }
        
    } elseif ($method === 'GET') {
        try {
            $stmt = $pdo->query("SELECT * FROM Profile");
            $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $profiles]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Failed to fetch profiles']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
}

function handleDashboard($pdo, $method) {
    if ($method === 'GET') {
        try {
            $stats = [];
            
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM User1");
            $stats['totalUsers'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM Transaction");
            $stats['totalTransactions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM Alert");
                $stats['suspiciousTransactions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                $stats['activeAlerts'] = $stats['suspiciousTransactions'];
            } catch (Exception $e) {
                $stats['suspiciousTransactions'] = 0;
                $stats['activeAlerts'] = 0;
            }
            
            $stmt = $pdo->query("
                SELECT t.*, u.Name as UserName 
                FROM Transaction t 
                LEFT JOIN User1 u ON t.UserID = u.UserID 
                ORDER BY t.Date DESC, t.Time DESC 
                LIMIT 10
            ");
            $stats['recentTransactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stats['fraudStats'] = [
                'flagged_transactions' => 0,
                'confirmed_fraud' => 0,
                'false_positives' => 0,
                'accuracy_rate' => 0
            ];
            
            echo json_encode(['success' => true, 'data' => $stats]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Failed to load dashboard: ' . $e->getMessage()]);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
}

function handleAlerts($pdo, $method, $segments) {
    try {
        $operation = $segments[1] ?? '';
        
        switch ($operation) {
            case 'dismiss':
                if ($method === 'POST') {
                    $input = file_get_contents('php://input');
                    $data = json_decode($input, true);
                    
                    if (isset($data['alertID'])) {
                        $stmt = $pdo->prepare("DELETE FROM Alert WHERE AlertID = ?");
                        $result = $stmt->execute([$data['alertID']]);
                        
                        if ($result) {
                            echo json_encode(['success' => true, 'data' => ['message' => 'Alert dismissed successfully']]);
                        } else {
                            echo json_encode(['success' => false, 'error' => 'Failed to dismiss alert']);
                        }
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Missing alert ID']);
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
                }
                break;
                
            default:
                $stmt = $pdo->query("
                    SELECT a.AlertID, 
                           a.AnomalyID, 
                           a.UserID, 
                           a.Timestamp,
                           'Security Alert' as AlertType,
                           'Suspicious activity detected' as Description,
                           'MEDIUM' as RiskLevel,
                           'active' as Status,
                           COALESCE(ar.TransactionID, 'N/A') as TransactionID,
                           COALESCE(t.Amount, 0) as Amount,
                           COALESCE(u.Name, 'Unknown User') as UserName
                    FROM Alert a 
                    LEFT JOIN AnomalyRecord ar ON a.AnomalyID = ar.AnomalyID
                    LEFT JOIN Transaction t ON ar.TransactionID = t.TransactionID
                    LEFT JOIN User1 u ON a.UserID = u.UserID 
                    ORDER BY a.Timestamp DESC 
                    LIMIT 100
                ");
                $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'data' => $alerts]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Alerts system error: ' . $e->getMessage()]);
    }
}
?>