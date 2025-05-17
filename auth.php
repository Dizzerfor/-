<?php
session_start();
require 'includes/db.php';

// Обработка форм
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['login'])) {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = $user['role'];
            header("Location: " . ($user['role'] === 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php'));
            exit();
        } else {
            $_SESSION['error'] = "Неверное имя пользователя или пароль";
            header("Location: auth.php");
            exit();
        }
    }
    
    if (isset($_POST['register'])) {
        $username = trim($_POST['username']);
        $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'user')");
            $stmt->execute([$username, $password]);
            $_SESSION['success'] = "Регистрация успешна! Войдите в систему";
            header("Location: auth.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Ошибка регистрации: " . $e->getMessage();
            $_SESSION['form_data'] = $_POST;
            header("Location: auth.php#register");
            exit();
        }
    }
}

// Получение данных формы
$error = $_SESSION['error'] ?? null;
$success = $_SESSION['success'] ?? null;
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['error'], $_SESSION['success'], $_SESSION['form_data']);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Авторизация</title>
    <link rel="stylesheet" href="css/auth.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php"><img src="image/logo.jpg" alt="Logo">RESPAWN</a>
            <div class="navbar-links">
                
            </div>
        </div>
    </nav>
    <div class="auth-container">
        <div class="particles">
            <?php for($i = 0; $i < 50; $i++): ?>
                <div class="particle" style="
                    width: <?= rand(2,5) ?>px;
                    height: <?= rand(2,5) ?>px;
                    left: <?= rand(0,100) ?>%;
                    animation-delay: <?= rand(0,20) ?>s;"></div>
            <?php endfor; ?>
        </div>
        
        <div class="auth-box">
    <div class="switch">
        <button id="loginBtn" class="active">Вход</button>
        <button id="registerBtn">Регистрация</button>
    </div>
    
    <div class="form-container">
        <!-- Форма входа -->
        <form id="loginForm" method="POST" class="active">
            <input type="text" name="username" placeholder="Имя пользователя" required>
            <input type="password" name="password" placeholder="Пароль" required>
            <button type="submit" name="login">ВОЙТИ</button>
        </form>

        <!-- Форма регистрации -->
        <form id="registerForm" method="POST">
            <input type="text" name="username" placeholder="Имя пользователя" required>
            <input type="password" name="password" placeholder="Пароль" required>
            <button type="submit" name="register">ЗАРЕГИСТРИРОВАТЬСЯ</button>
        </form>
    </div>
</div>

    <script>
document.getElementById('loginBtn').addEventListener('click', function() {
    this.classList.add('active');
    document.getElementById('registerBtn').classList.remove('active');
    document.getElementById('loginForm').classList.add('active');
    document.getElementById('registerForm').classList.remove('active');
});

document.getElementById('registerBtn').addEventListener('click', function() {
    this.classList.add('active');
    document.getElementById('loginBtn').classList.remove('active');
    document.getElementById('registerForm').classList.add('active');
    document.getElementById('loginForm').classList.remove('active');
});
    </script>
</body>
</html>