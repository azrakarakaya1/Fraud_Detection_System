<?php
// fraud_detection.php - Fixed with proper alert and anomaly record integration

class FraudDetector {
    private $pdo;
    
    public function __construct($database_connection) {
        $this->pdo = $database_connection;
    }
    
    /**
     * Main fraud detection function
     * Analyzes a transaction and returns risk assessment
     */
    public function analyzeTransaction($transactionData) {
        $riskScore = 0;
        $flags = [];
        $alertCreated = false;
        
        $amount = floatval($transactionData['amount']);
        $userID = $transactionData['transactionUserID'];
        $transactionID = $transactionData['transactionID'];
        
        // Simple suspicious transaction detection
        $isSuspicious = false;
        
        // Check 1: High amount transactions
        if ($amount >= 15000) {
            $riskScore += 60;
            $flags[] = "Very high amount transaction (₺" . number_format($amount, 2) . ")";
            $isSuspicious = true;
        } elseif ($amount >= 10000) {
            $riskScore += 40;
            $flags[] = "High amount transaction (₺" . number_format($amount, 2) . ")";
            $isSuspicious = true;
        }
        
        // Check 2: Get user profile and check against limits
        $userProfile = $this->getUserProfile($userID);
        if ($userProfile && !empty($userProfile['CategoryLimits'])) {
            $categoryLimits = json_decode($userProfile['CategoryLimits'], true);
            $categoryID = $transactionData['categoryID'] ?? 'C001';
            
            if ($categoryLimits && isset($categoryLimits[$categoryID])) {
                $limit = floatval($categoryLimits[$categoryID]);
                if ($amount > $limit) {
                    $riskScore += 30;
                    $flags[] = "Amount exceeds category limit (₺" . number_format($limit, 2) . ")";
                    $isSuspicious = true;
                }
            }
        }
        
        // Check 3: Transaction frequency
        $userHistory = $this->getUserTransactionHistory($userID, 1); // Last 1 day
        $todayCount = count($userHistory);
        
        if ($todayCount >= 5) {
            $riskScore += 25;
            $flags[] = "High transaction frequency today ($todayCount transactions)";
            $isSuspicious = true;
        }
        
        // Determine risk level
        $riskLevel = $this->calculateRiskLevel($riskScore);
        
        // CREATE ALERT if transaction is suspicious
        if ($isSuspicious && $riskScore >= 25) {
            $alertID = $this->createSimpleAlert($transactionData, $riskLevel, $flags);
            if ($alertID) {
                $alertCreated = true;
                error_log("🚨 FRAUD ALERT CREATED: AlertID=$alertID for User=$userID, Amount=$amount, RiskScore=$riskScore");
            }
        }
        
        return [
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'flags' => $flags,
            'recommendation' => $this->getRecommendation($riskLevel),
            'alert_created' => $alertCreated
        ];
    }
    private function createSimpleAlert($transactionData, $riskLevel, $flags) {
        try {
            // Generate unique IDs
            $timestamp = time();
            $random = rand(100, 999);
            $alertID = 'AL' . substr($timestamp, -6) . $random;
            $anomalyID = 'AN' . substr($timestamp, -6) . $random;
            
            // Make sure AlertID is unique
            $checkStmt = $this->pdo->prepare("SELECT AlertID FROM Alert WHERE AlertID = ?");
            $checkStmt->execute([$alertID]);
            
            while ($checkStmt->rowCount() > 0) {
                $random = rand(100, 999);
                $alertID = 'AL' . substr($timestamp, -6) . $random;
                $checkStmt->execute([$alertID]);
            }
            
            // Make sure AnomalyID is unique
            $checkStmt = $this->pdo->prepare("SELECT AnomalyID FROM AnomalyRecord WHERE AnomalyID = ?");
            $checkStmt->execute([$anomalyID]);
            
            while ($checkStmt->rowCount() > 0) {
                $random = rand(100, 999);
                $anomalyID = 'AN' . substr($timestamp, -6) . $random;
                $checkStmt->execute([$anomalyID]);
            }
            
            // Create AnomalyRecord table if needed
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS AnomalyRecord (
                    AnomalyID VARCHAR(20) PRIMARY KEY,
                    TransactionID VARCHAR(20) NOT NULL,
                    UserID VARCHAR(20) NOT NULL,
                    AnomalyType VARCHAR(50) NOT NULL,
                    DetectedDate DATE NOT NULL,
                    Status VARCHAR(50) DEFAULT 'pending_review'
                )
            ");
            
            // Determine anomaly type
            $amount = floatval($transactionData['amount']);
            if ($amount >= 30000) {
                $anomalyType = 'critical_amount';
            } elseif ($amount >= 15000) {
                $anomalyType = 'very_high_amount';
            } elseif ($amount >= 10000) {
                $anomalyType = 'high_amount';
            } else {
                $anomalyType = 'suspicious_activity';
            }
            
            // Step 1: Create anomaly record
            $stmt = $this->pdo->prepare("
                INSERT INTO AnomalyRecord (AnomalyID, TransactionID, UserID, AnomalyType, DetectedDate, Status) 
                VALUES (?, ?, ?, ?, CURDATE(), 'pending_review')
            ");
            
            $anomalyResult = $stmt->execute([
                $anomalyID,
                $transactionData['transactionID'],
                $transactionData['transactionUserID'],
                $anomalyType
            ]);
            
            if (!$anomalyResult) {
                error_log("❌ Failed to create anomaly record");
                return false;
            }
            
            // Step 2: Create alert
            $stmt = $this->pdo->prepare("
                INSERT INTO Alert (AlertID, AnomalyID, UserID, Timestamp) 
                VALUES (?, ?, ?, NOW())
            ");
            
            $alertResult = $stmt->execute([
                $alertID,
                $anomalyID,
                $transactionData['transactionUserID']
            ]);
            
            if ($alertResult) {
                error_log("✅ SUCCESS: Alert created! AlertID=$alertID, UserID=" . $transactionData['transactionUserID'] . ", AnomalyType=$anomalyType");
                return $alertID;
            } else {
                error_log("❌ Failed to create alert");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("❌ Error creating fraud alert: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Check for unusual transaction amounts
     */
    private function checkAmountAnomaly($transaction, $profile, $history) {
        $riskScore = 0;
        $flags = [];
        $anomalies = [];
        
        $amount = floatval($transaction['amount']);
        
        // ENHANCED: Simple high amount detection
        if ($amount >= 15000) {
            $riskScore += 50;
            $flags[] = "Very high amount transaction (₺" . number_format($amount, 2) . ")";
            $anomalies[] = [
                'type' => 'very_high_amount',
                'description' => "Transaction amount ₺" . number_format($amount, 2) . " is extremely high",
                'severity' => 'HIGH'
            ];
        } elseif ($amount >= 10000) {
            $riskScore += 35;
            $flags[] = "High amount transaction (₺" . number_format($amount, 2) . ")";
            $anomalies[] = [
                'type' => 'high_amount',
                'description' => "Transaction amount ₺" . number_format($amount, 2) . " exceeds normal limits",
                'severity' => 'MEDIUM'
            ];
        } elseif ($amount >= 5000) {
            $riskScore += 20;
            $flags[] = "Above average amount (₺" . number_format($amount, 2) . ")";
            $anomalies[] = [
                'type' => 'above_average_amount',
                'description' => "Transaction amount ₺" . number_format($amount, 2) . " is above average",
                'severity' => 'LOW'
            ];
        }
        
        // Check against user's average monthly spend
        if ($profile && $profile['AvgMonthlySpend'] > 0) {
            $avgMonthly = floatval($profile['AvgMonthlySpend']);
            
            if ($amount > ($avgMonthly * 5)) {
                $riskScore += 40;
                $flags[] = "Amount is " . round($amount / $avgMonthly, 1) . "x higher than monthly average";
                $anomalies[] = [
                    'type' => 'extremely_high_vs_profile',
                    'description' => "Amount is " . round($amount / $avgMonthly, 1) . "x higher than user's monthly average",
                    'severity' => 'HIGH'
                ];
            } elseif ($amount > ($avgMonthly * 2)) {
                $riskScore += 25;
                $flags[] = "Amount is " . round($amount / $avgMonthly, 1) . "x higher than monthly average";
                $anomalies[] = [
                    'type' => 'high_vs_profile',
                    'description' => "Amount significantly exceeds user's typical spending pattern",
                    'severity' => 'MEDIUM'
                ];
            }
        }
        
        // Check against historical transaction patterns
        if (!empty($history)) {
            $amounts = array_column($history, 'Amount');
            $avgAmount = array_sum($amounts) / count($amounts);
            $maxAmount = max($amounts);
            
            if ($amount > ($maxAmount * 2)) {
                $riskScore += 30;
                $flags[] = "Amount is 2x higher than previous maximum transaction";
                $anomalies[] = [
                    'type' => 'exceeds_historical_max',
                    'description' => "Amount doubles previous maximum transaction of ₺" . number_format($maxAmount, 2),
                    'severity' => 'HIGH'
                ];
            }
            
            $stdDev = $this->calculateStandardDeviation($amounts);
            if ($stdDev > 0 && abs($amount - $avgAmount) > (3 * $stdDev)) {
                $riskScore += 20;
                $flags[] = "Amount is a statistical outlier";
                $anomalies[] = [
                    'type' => 'statistical_outlier',
                    'description' => "Amount deviates significantly from user's spending pattern",
                    'severity' => 'MEDIUM'
                ];
            }
        }
        
        // Round number amounts (often fraud indicators)
        if ($amount >= 1000 && $amount % 1000 == 0) {
            $riskScore += 10;
            $flags[] = "Round number amount";
            $anomalies[] = [
                'type' => 'round_number',
                'description' => "Exact round number amount may indicate fraudulent behavior",
                'severity' => 'LOW'
            ];
        }
        
        return [
            'risk_score' => $riskScore,
            'flags' => $flags,
            'anomalies' => $anomalies
        ];
    }
    
    /**
     * Check for unusual transaction times
     */
    private function checkTimeAnomaly($transaction, $profile) {
        $riskScore = 0;
        $flags = [];
        $anomalies = [];
        
        $transactionTime = strtotime($transaction['time']);
        $hour = date('H', $transactionTime);
        $dayOfWeek = date('w', strtotime($transaction['date'])); // 0 = Sunday
        
        // Check against user's usual time windows
        if ($profile && !empty($profile['TimeWindows'])) {
            $timeWindows = $this->parseTimeWindows($profile['TimeWindows']);
            if (!$this->isTimeInWindows($hour, $timeWindows)) {
                $riskScore += 25;
                $flags[] = "Transaction outside usual time window";
                $anomalies[] = [
                    'type' => 'unusual_time',
                    'description' => "Transaction at {$hour}:00 is outside user's normal activity window",
                    'severity' => 'MEDIUM'
                ];
            }
        } else {
            // Default suspicious hours if no profile exists
            if ($hour >= 0 && $hour <= 5) {
                $riskScore += 20;
                $flags[] = "Late night transaction";
                $anomalies[] = [
                    'type' => 'late_night',
                    'description' => "Transaction occurred during late night hours ({$hour}:00)",
                    'severity' => 'MEDIUM'
                ];
            }
        }
        
        // Weekend high-value transactions
        if (($dayOfWeek == 0 || $dayOfWeek == 6) && floatval($transaction['amount']) > 1000) {
            $riskScore += 15;
            $flags[] = "High-value weekend transaction";
            $anomalies[] = [
                'type' => 'weekend_high_value',
                'description' => "High-value transaction on weekend",
                'severity' => 'LOW'
            ];
        }
        
        return [
            'risk_score' => $riskScore,
            'flags' => $flags,
            'anomalies' => $anomalies
        ];
    }
    
    /**
     * Check for unusual locations
     */
    private function checkLocationAnomaly($transaction, $profile) {
        $riskScore = 0;
        $flags = [];
        $anomalies = [];
        
        $location = $transaction['location'];
        
        // Check against frequent locations
        if ($profile && !empty($profile['FrequentLocations'])) {
            $frequentLocations = explode(',', $profile['FrequentLocations']);
            $frequentLocations = array_map('trim', $frequentLocations);
            
            $isFrequentLocation = false;
            foreach ($frequentLocations as $freqLocation) {
                if (stripos($location, $freqLocation) !== false) {
                    $isFrequentLocation = true;
                    break;
                }
            }
            
            if (!$isFrequentLocation) {
                $riskScore += 20;
                $flags[] = "Transaction from unusual location";
                $anomalies[] = [
                    'type' => 'unusual_location',
                    'description' => "Transaction from {$location} - not in user's frequent locations",
                    'severity' => 'MEDIUM'
                ];
            }
        }
        
        return [
            'risk_score' => $riskScore,
            'flags' => $flags,
            'anomalies' => $anomalies
        ];
    }
    
    /**
     * Check transaction frequency patterns
     */
    private function checkFrequencyAnomaly($transaction, $history) {
        $riskScore = 0;
        $flags = [];
        $anomalies = [];
        
        if (empty($history)) {
            return ['risk_score' => 0, 'flags' => [], 'anomalies' => []];
        }
        
        $today = date('Y-m-d');
        $todayTransactions = 0;
        $last24Hours = 0;
        
        foreach ($history as $histTransaction) {
            if ($histTransaction['Date'] == $today) {
                $todayTransactions++;
            }
            
            $transactionTime = strtotime($histTransaction['Date'] . ' ' . $histTransaction['Time']);
            if ($transactionTime > (time() - 86400)) {
                $last24Hours++;
            }
        }
        
        // Too many transactions in one day
        if ($todayTransactions >= 10) {
            $riskScore += 30;
            $flags[] = "Unusual number of transactions today ($todayTransactions)";
            $anomalies[] = [
                'type' => 'high_frequency_daily',
                'description' => "Unusually high number of transactions today: $todayTransactions",
                'severity' => 'HIGH'
            ];
        } elseif ($todayTransactions >= 5) {
            $riskScore += 15;
            $flags[] = "Above average transactions today ($todayTransactions)";
            $anomalies[] = [
                'type' => 'moderate_frequency_daily',
                'description' => "Above average transactions today: $todayTransactions",
                'severity' => 'MEDIUM'
            ];
        }
        
        // Too many transactions in 24 hours
        if ($last24Hours >= 8) {
            $riskScore += 25;
            $flags[] = "High transaction frequency in last 24 hours ($last24Hours)";
            $anomalies[] = [
                'type' => 'high_frequency_24h',
                'description' => "High transaction frequency: $last24Hours transactions in 24 hours",
                'severity' => 'HIGH'
            ];
        }
        
        return [
            'risk_score' => $riskScore,
            'flags' => $flags,
            'anomalies' => $anomalies
        ];
    }
    
    /**
     * Check transaction velocity (rapid successive transactions)
     */
    private function checkVelocityAnomaly($transaction, $history) {
        $riskScore = 0;
        $flags = [];
        $anomalies = [];
        
        if (empty($history)) {
            return ['risk_score' => 0, 'flags' => [], 'anomalies' => []];
        }
        
        // Check for transactions within last hour
        $recentTransactions = [];
        $currentTime = time();
        
        foreach ($history as $histTransaction) {
            $transactionTime = strtotime($histTransaction['Date'] . ' ' . $histTransaction['Time']);
            if ($transactionTime > ($currentTime - 3600)) {
                $recentTransactions[] = $histTransaction;
            }
        }
        
        if (count($recentTransactions) >= 3) {
            $riskScore += 40;
            $flags[] = "Multiple transactions within last hour";
            $anomalies[] = [
                'type' => 'high_velocity',
                'description' => "Multiple rapid transactions: " . count($recentTransactions) . " in last hour",
                'severity' => 'HIGH'
            ];
        } elseif (count($recentTransactions) >= 2) {
            $riskScore += 20;
            $flags[] = "Rapid successive transactions";
            $anomalies[] = [
                'type' => 'moderate_velocity',
                'description' => "Rapid successive transactions detected",
                'severity' => 'MEDIUM'
            ];
        }
        
        return [
            'risk_score' => $riskScore,
            'flags' => $flags,
            'anomalies' => $anomalies
        ];
    }
    
    /**
     * Check spending patterns by category
     */
    private function checkCategoryAnomaly($transaction, $profile, $history) {
        $riskScore = 0;
        $flags = [];
        $anomalies = [];
        
        $categoryID = $transaction['categoryID'];
        $amount = floatval($transaction['amount']);
        
        // Check against category limits in profile
        if ($profile && !empty($profile['CategoryLimits'])) {
            $categoryLimits = json_decode($profile['CategoryLimits'], true);
            if ($categoryLimits && isset($categoryLimits[$categoryID])) {
                $limit = floatval($categoryLimits[$categoryID]);
                if ($amount > $limit) {
                    $riskScore += 30;
                    $flags[] = "Amount exceeds category limit";
                    $anomalies[] = [
                        'type' => 'exceeds_category_limit',
                        'description' => "Amount ₺" . number_format($amount, 2) . " exceeds category limit of ₺" . number_format($limit, 2),
                        'severity' => 'HIGH'
                    ];
                }
            }
        }
        
        // Check historical spending in this category
        if (!empty($history)) {
            $categoryHistory = array_filter($history, function($t) use ($categoryID) {
                return $t['CategoryID'] == $categoryID;
            });
            
            if (!empty($categoryHistory)) {
                $categoryAmounts = array_column($categoryHistory, 'Amount');
                $avgCategoryAmount = array_sum($categoryAmounts) / count($categoryAmounts);
                
                if ($amount > ($avgCategoryAmount * 3)) {
                    $riskScore += 25;
                    $flags[] = "Amount is unusually high for this category";
                    $anomalies[] = [
                        'type' => 'unusual_category_amount',
                        'description' => "Amount is 3x higher than average for this category",
                        'severity' => 'MEDIUM'
                    ];
                }
            }
        }
        
        return [
            'risk_score' => $riskScore,
            'flags' => $flags,
            'anomalies' => $anomalies
        ];
    }
    
    /**
     * Calculate overall risk level based on score
     */
    private function calculateRiskLevel($riskScore) {
        if ($riskScore >= 80) {
            return 'CRITICAL';
        } elseif ($riskScore >= 60) {
            return 'HIGH';
        } elseif ($riskScore >= 30) {
            return 'MEDIUM';
        } elseif ($riskScore >= 15) {
            return 'LOW_MEDIUM';
        } else {
            return 'LOW';
        }
    }
    
    /**
     * Get recommendation based on risk level
     */
    private function getRecommendation($riskLevel) {
        switch ($riskLevel) {
            case 'CRITICAL':
                return 'BLOCK TRANSACTION - Immediate manual review required';
            case 'HIGH':
                return 'REQUIRE ADDITIONAL VERIFICATION - Contact user immediately';
            case 'MEDIUM':
                return 'FLAG FOR REVIEW - Monitor closely and review within 24 hours';
            case 'LOW_MEDIUM':
                return 'MONITOR - Log for pattern analysis';
            default:
                return 'APPROVE - Normal transaction';
        }
    }
    
    /**
     * FIXED: Create anomaly records in database
     */
    private function createAnomalyRecords($transactionData, $anomalies, $riskLevel) {
        $anomalyRecords = [];
        
        try {
            // Create AnomalyRecord table if it doesn't exist
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `AnomalyRecord` (
                    `AnomalyID` VARCHAR(50) PRIMARY KEY,
                    `TransactionID` VARCHAR(50) NOT NULL,
                    `UserID` VARCHAR(50) NOT NULL,
                    `AnomalyType` VARCHAR(100) NOT NULL,
                    `Description` TEXT,
                    `Severity` VARCHAR(20) NOT NULL,
                    `DetectedDate` DATETIME NOT NULL,
                    `Status` VARCHAR(50) DEFAULT 'pending_review'
                )
            ");
            
            foreach ($anomalies as $anomaly) {
                // Generate unique anomaly ID
                $microtime = round(microtime(true) * 1000);
                $random = rand(100, 999);
                $anomalyID = 'AN' . substr($microtime, -6) . $random;
                
                // Check if AnomalyID already exists
                $checkStmt = $this->pdo->prepare("SELECT AnomalyID FROM `AnomalyRecord` WHERE AnomalyID = ?");
                $checkStmt->execute([$anomalyID]);
                
                while ($checkStmt->rowCount() > 0) {
                    $random = rand(100, 999);
                    $anomalyID = 'AN' . substr($microtime, -6) . $random;
                    $checkStmt->execute([$anomalyID]);
                }
                
                // Insert anomaly record
                $stmt = $this->pdo->prepare("
                    INSERT INTO `AnomalyRecord` (AnomalyID, TransactionID, UserID, AnomalyType, Description, Severity, DetectedDate, Status) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), 'pending_review')
                ");
                
                $result = $stmt->execute([
                    $anomalyID,
                    $transactionData['transactionID'],
                    $transactionData['transactionUserID'],
                    $anomaly['type'],
                    $anomaly['description'],
                    $anomaly['severity']
                ]);
                
                if ($result) {
                    $anomalyRecords[] = $anomalyID;
                    error_log("✅ Anomaly record created: $anomalyID ({$anomaly['type']})");
                } else {
                    error_log("❌ Failed to create anomaly record for {$anomaly['type']}");
                }
            }
            
        } catch (Exception $e) {
            error_log("❌ Exception creating anomaly records: " . $e->getMessage());
        }
        
        return $anomalyRecords;
    }
    
    /**
     * FIXED: Create fraud alert linked to anomaly records
     */
    private function createFraudAlert($transactionData, $riskLevel, $flags, $anomalyRecords) {
        try {
            // Generate unique alert ID
            $microtime = round(microtime(true) * 1000);
            $random = rand(100, 999);
            $alertID = 'AL' . substr($microtime, -6) . $random;
            
            // Use first anomaly record ID or generate one
            $anomalyID = !empty($anomalyRecords) ? $anomalyRecords[0] : 'A' . substr($microtime, -6) . $random;
            
            // Check if AlertID already exists
            $checkStmt = $this->pdo->prepare("SELECT AlertID FROM `Alert` WHERE AlertID = ?");
            $checkStmt->execute([$alertID]);
            
            while ($checkStmt->rowCount() > 0) {
                $random = rand(100, 999);
                $alertID = 'AL' . substr($microtime, -6) . $random;
                $checkStmt->execute([$alertID]);
            }
            
            // Insert alert using your exact table structure
            $stmt = $this->pdo->prepare("
                INSERT INTO `Alert` (AlertID, AnomalyID, UserID, Timestamp) 
                VALUES (?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([
                $alertID,
                $anomalyID,
                $transactionData['transactionUserID']
            ]);
            
            if ($result) {
                error_log("✅ Fraud alert created successfully: AlertID=$alertID, UserID=" . $transactionData['transactionUserID'] . ", RiskLevel=$riskLevel");
                
                // Log the alert details
                $flagsText = implode(', ', array_slice($flags, 0, 3));
                error_log("🔍 Alert details: Flags=[$flagsText], AnomalyRecords=[" . implode(', ', $anomalyRecords) . "]");
                
                return $alertID;
            } else {
                error_log("❌ Failed to create fraud alert in database");
                $errorInfo = $stmt->errorInfo();
                error_log("❌ Database error: " . print_r($errorInfo, true));
                return false;
            }
            
        } catch (Exception $e) {
            error_log("❌ Exception creating fraud alert: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Helper functions
     */
    private function getUserProfile($userID) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM `Profile` WHERE UserID = ?");
            $stmt->execute([$userID]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting user profile: " . $e->getMessage());
            return null;
        }
    }
    
    private function getUserTransactionHistory($userID, $limitDays = 30) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM `Transaction` 
                WHERE UserID = ? AND Date >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ORDER BY Date DESC, Time DESC
            ");
            $stmt->execute([$userID, $limitDays]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting transaction history: " . $e->getMessage());
            return [];
        }
    }
    
    private function calculateStandardDeviation($values) {
        $count = count($values);
        if ($count < 2) return 0;
        
        $mean = array_sum($values) / $count;
        $squaredDiffs = array_map(function($value) use ($mean) {
            return pow($value - $mean, 2);
        }, $values);
        
        return sqrt(array_sum($squaredDiffs) / ($count - 1));
    }
    
    private function parseTimeWindows($timeWindowsStr) {
        $windows = [];
        $parts = explode(',', $timeWindowsStr);
        
        foreach ($parts as $part) {
            if (preg_match('/(\d{2}):(\d{2})-(\d{2}):(\d{2})/', trim($part), $matches)) {
                $windows[] = [
                    'start' => intval($matches[1]),
                    'end' => intval($matches[3])
                ];
            }
        }
        
        return $windows;
    }
    
    private function isTimeInWindows($hour, $timeWindows) {
        foreach ($timeWindows as $window) {
            if ($hour >= $window['start'] && $hour <= $window['end']) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get fraud statistics for dashboard
     */
    public function getFraudStatistics($days = 30) {
        $stats = [];
        
        try {
            // Total flagged transactions
            try {
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM `AnomalyRecord` 
                    WHERE DetectedDate >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ");
                $stmt->execute([$days]);
                $stats['flagged_transactions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            } catch (Exception $e) {
                $stats['flagged_transactions'] = 0;
            }
            
            // Confirmed fraud cases
            try {
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM `AnomalyRecord` 
                    WHERE Status = 'confirmed_fraud' AND DetectedDate >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ");
                $stmt->execute([$days]);
                $stats['confirmed_fraud'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            } catch (Exception $e) {
                $stats['confirmed_fraud'] = 0;
            }
            
            // False positives
            try {
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM `AnomalyRecord` 
                    WHERE Status = 'false_alarm' AND DetectedDate >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ");
                $stmt->execute([$days]);
                $stats['false_positives'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            } catch (Exception $e) {
                $stats['false_positives'] = 0;
            }
            
            // Calculate accuracy rate
            $total = $stats['confirmed_fraud'] + $stats['false_positives'];
            $stats['accuracy_rate'] = $total > 0 ? round(($stats['confirmed_fraud'] / $total) * 100, 2) : 0;
            
        } catch (Exception $e) {
            error_log("Error getting fraud statistics: " . $e->getMessage());
            $stats = [
                'flagged_transactions' => 0,
                'confirmed_fraud' => 0,
                'false_positives' => 0,
                'accuracy_rate' => 0
            ];
        }
        
        return $stats;
    }
}
?>