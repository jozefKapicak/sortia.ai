<?php
// --- get_visit_details.php ---
// Handles AJAX requests to fetch details for a specific visit ID.
// Previous/Next logic is now handled entirely client-side (ID +/- 1).

session_start(); 
require dirname(__DIR__) . '/vendor/autoload.php';

header('Content-Type: application/json'); 

// --- Basic Security Check ---
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// --- Get Request Parameters ---
$visit_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Initialize response (no prev/next needed from backend)
$response = ['error' => null, 'data' => null]; 

if (!$visit_id || $visit_id < 1) { 
    $response['error'] = 'Invalid Visit ID requested.';
    echo json_encode($response);
    exit();
}

// --- Load .env variables ---
try {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->load();
    $dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'])->notEmpty();
} catch (Exception $e) {
    error_log('AJAX Visit Details DB Config Error: ' . $e->getMessage());
    $response['error'] = 'Server configuration error.';
    echo json_encode($response);
    exit();
}

// --- Database Connection ---
$pdo = null;
try {
     $pdo = new PDO(
        "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'] . ";charset=utf8mb4",
        $_ENV['DB_USER'],
        $_ENV['DB_PASS']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("AJAX Visit Details DB Connection Error: " . $e->getMessage());
    $response['error'] = 'Database connection failed.';
    echo json_encode($response);
    exit();
}

// --- Fetch Current Visit Details ---
try {
    $stmt = $pdo->prepare("SELECT * FROM page_visits WHERE id = :id");
    $stmt->bindParam(':id', $visit_id, PDO::PARAM_INT);
    $stmt->execute();
    $visit_details = $stmt->fetch();

    if (!$visit_details) {
        $response['error'] = 'Visit not found for ID: ' . $visit_id; 
    } else {
        // Format data for consistency
         $response['data'] = [
            'id' => $visit_details['id'] ?? $visit_id, 
            'time' => date('Y-m-d H:i:s', strtotime($visit_details['visit_time'])), 
            'ip' => $visit_details['ip_address'] ?? '-',
            'lang' => $visit_details['language'] ?? '-',
            'url' => $visit_details['requested_url'] ?? '-',
            'referrer' => $visit_details['referrer'] ?? '-',
            'ua' => $visit_details['user_agent'] ?? '-'
        ];
    }

} catch (PDOException $e) {
    error_log("Error fetching visit details (ID: $visit_id): " . $e->getMessage());
    $response['error'] = 'Error fetching visit details.';
}

// --- Output the JSON response ---
// It will contain either 'data' or 'error'.
echo json_encode($response);
exit();

?>
