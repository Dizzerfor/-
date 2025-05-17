<?php
session_start();
$pdo = new PDO('mysql:host=localhost;dbname=gaming_club', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function getPCs(): array {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM pcs");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getGamesByPC($pc_id): array {
    global $pdo;
    $stmt = $pdo->prepare(query: "SELECT * FROM games WHERE pc_id = ?");
    $stmt->execute(params: [$pc_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "gaming_club";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}

function authenticateUser($username, $password) {
    global $conn;
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }
    return false;
}


function registerUser($username, $password) {
    global $conn;
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, 'user')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $hashedPassword);

    return $stmt->execute();
}
function logAction($action, $target_id = null, $details = '') {
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'];
    
    $stmt = $pdo->prepare("
        INSERT INTO logs 
        (action_type, user_id, target_id, details, ip_address) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $action,
        $_SESSION['user_id'],
        $target_id,
        $details,
        $ip
    ]);
}
?>
