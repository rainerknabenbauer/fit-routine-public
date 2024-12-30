<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Configuration
define('ADMIN_TOKEN', 'exercise_admin_token_123');
define('ADMIN_EMAIL', 'rainer.knabenbauer@posteo.de');

$db_config = [
    'host' => 'localhost',
    'dbname' => 'd04256cf',
    'user' => 'd04256cf',
    'password' => 'PZNtqSCofaVfXey4EwQk'
];

try {
    $pdo = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8mb4",
        $db_config['user'],
        $db_config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path = str_replace('/api', '', $path);

// Combined routing
switch ($path) {
    // User routes
    case '/user/register':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            registerUser($pdo);
        }
        break;

    case '/user/verify':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verifyUser($pdo);
        }
        break;

    case '/exercise/random':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            getRandomExercise($pdo);
        }
        break;

    case '/exercise/complete':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            markExerciseComplete($pdo);
        }
        break;

    case '/progress/today':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            getTodayProgress($pdo);
        }
        break;

    // Admin routes
    case '/admin/send-login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            sendLoginLink();
        }
        break;

    case '/admin/verify-token':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verifyToken($pdo);
        }
        break;

    case '/admin/exercises':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            getExercises($pdo);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            createExercise($pdo);
        }
        break;

    case '/admin/exercise':
        if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            updateExercise($pdo);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            deleteExercise($pdo);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}

// User functions
function registerUser($pdo) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid email address']);
            return;
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            echo json_encode(['userId' => $user['id']]);
            return;
        }

        $stmt = $pdo->prepare('INSERT INTO users (email) VALUES (?)');
        $stmt->execute([$email]);
        
        echo json_encode(['userId' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to register user']);
    }
}

function verifyUser($pdo) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'] ?? '';

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            echo json_encode(['userId' => $user['id']]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to verify user']);
    }
}

function markExerciseComplete($pdo) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $exerciseId = $data['exerciseId'] ?? null;
        $userId = $data['userId'] ?? null;

        if (!$exerciseId || !$userId) {
            http_response_code(400);
            echo json_encode(['error' => 'Exercise ID and User ID are required']);
            return;
        }

        $stmt = $pdo->prepare('INSERT INTO completed_exercises (user_id, exercise_id) VALUES (?, ?)');
        $stmt->execute([$userId, $exerciseId]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to mark exercise as completed']);
    }
}

function getTodayProgress($pdo) {
    try {
        $userId = $_GET['userId'] ?? null;

        if (!$userId) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID is required']);
            return;
        }

        $stmt = $pdo->prepare('
            SELECT COUNT(*) as count 
            FROM completed_exercises 
            WHERE user_id = ? AND DATE(completed_at) = CURDATE()
        ');
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['completed' => (int)$result['count']]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch progress']);
    }
}

// Admin functions
function sendLoginLink() {
    $loginUrl = 'https://routine.nykon.de/admin?token=' . ADMIN_TOKEN;
    $to = ADMIN_EMAIL;
    $subject = 'Exercise Admin Login Link';
    $message = "Click here to login to the admin interface: $loginUrl";
    $headers = 'From: noreply@routine.nykon.de' . "\r\n";

    if (mail($to, $subject, $message, $headers)) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to send email']);
    }
}

function verifyToken($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    $token = $data['token'] ?? '';

    if ($token === ADMIN_TOKEN) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
    }
}

function verifyAdminToken() {
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? '';
    $token = str_replace('Bearer ', '', $token);

    if ($token !== ADMIN_TOKEN) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

function createExercise($pdo) {
    verifyAdminToken();
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $pdo->prepare('
            INSERT INTO exercises (name, description, image_url, sets, repetitions, type) 
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $data['name'],
            $data['description'],
            $data['image_url'],
            $data['sets'],
            $data['repetitions'],
            $data['type']
        ]);
        
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create exercise']);
    }
}

function updateExercise($pdo) {
    verifyAdminToken();
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $pdo->prepare('
            UPDATE exercises 
            SET name = ?, description = ?, image_url = ?, sets = ?, repetitions = ?, type = ?
            WHERE id = ?
        ');
        
        $stmt->execute([
            $data['name'],
            $data['description'],
            $data['image_url'],
            $data['sets'],
            $data['repetitions'],
            $data['type'],
            $data['id']
        ]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update exercise']);
    }
}

// In getRandomExercise function
function getRandomExercise($pdo) {
    try {
        $stmt = $pdo->query('SELECT * FROM exercises WHERE deleted = FALSE ORDER BY RAND() LIMIT 1');
        $exercise = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($exercise);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch exercise']);
    }
}

// In getExercises function (admin)
function getExercises($pdo) {
    verifyAdminToken();
    
    try {
        $stmt = $pdo->query('SELECT * FROM exercises ORDER BY id DESC');  // Show all exercises to admin
        $exercises = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['exercises' => $exercises]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch exercises']);
    }
}

// Replace deleteExercise with this soft delete version
function deleteExercise($pdo) {
    verifyAdminToken();
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Exercise ID is required']);
            return;
        }

        // Check if exercise exists and isn't already deleted
        $checkStmt = $pdo->prepare('SELECT id FROM exercises WHERE id = ? AND deleted = FALSE');
        $checkStmt->execute([$data['id']]);
        if (!$checkStmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Exercise not found or already deleted']);
            return;
        }

        // Perform soft delete
        $stmt = $pdo->prepare('
            UPDATE exercises 
            SET deleted = TRUE, 
                deleted_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ');
        
        $result = $stmt->execute([$data['id']]);
        
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete exercise']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete exercise']);
    }
}

// Optional: Add a restore function for admin
function restoreExercise($pdo) {
    verifyAdminToken();
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Exercise ID is required']);
            return;
        }

        $stmt = $pdo->prepare('
            UPDATE exercises 
            SET deleted = FALSE, 
                deleted_at = NULL 
            WHERE id = ?
        ');
        
        $result = $stmt->execute([$data['id']]);
        
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to restore exercise']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to restore exercise']);
    }
}