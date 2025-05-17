<?php
include 'includes/functions.php';
include 'includes/header.php';

$pcs = getPCs();
foreach ($pcs as &$pc) {
    $games = getGamesByPC($pc['id']);
    $pc['games'] = array_map(fn($game) => $game['game_name'], $games);
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Respawn Gaming Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Навигация -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><img src="image/logo.jpg" alt="Logo">RESPAWN</a>
            <div class="navbar-links">
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <!-- Если пользователь не авторизован, показываем кнопку авторизации -->
                    <a href="auth.php" class="cta-button">Авторизация</a>
                <?php elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin'): ?>
                    <!-- Если пользователь авторизован как администратор -->
                    <a href="admin_dashboard.php" class="cta-button">Админ панель</a>
                <?php else: ?>
                    <!-- Если пользователь авторизован как обычный пользователь -->
                    <a href="user_dashboard.php" class="cta-button">Личный кабинет</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Герой-секция -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="display-4">Добро пожаловать в Respawn Gaming Club!</h1>
            <p class="lead">Погрузитесь в мир высококачественных игр на мощных ПК.</p>
            <button class="cta-button" id="showSpecs">
                Показать характеристики
            </button>
            <button class="cta-button" id="hideSpecs" style="display: none;">
                Скрыть характеристики
            </button>
        </div>
    </section>

    <!-- Секция с характеристиками ПК -->
    <div class="container py-5" id="specsSection" style="display: none;">
        <div class="row" id="pcContainer">
            <?php foreach ($pcs as $pc): ?>
                <div class="col-md-4">
                    <div class="pc-card">
                        <h3><?= htmlspecialchars($pc['name']) ?></h3>
                        <p><strong>CPU:</strong> <?= htmlspecialchars($pc['cpu']) ?></p>
                        <p><strong>GPU:</strong> <?= htmlspecialchars($pc['gpu']) ?></p>
                        <p><strong>RAM:</strong> <?= htmlspecialchars($pc['ram']) ?></p>
                        <div class="games">
                            <?php foreach ($pc['games'] as $game): ?>
                                <span class="badge bg-primary"><?= htmlspecialchars($game) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $('#showSpecs').click(function() {
            // Показываем секцию с характеристиками с анимацией
            $('#specsSection').stop(true, true).slideDown(500).addClass('show');
            // Плавное скрытие кнопки "Показать характеристики"
            $(this).fadeOut(300, function() {
                // Плавное появление кнопки "Скрыть характеристики"
                $('#hideSpecs').fadeIn(300);
            });

            // Прокручиваем страницу до секции с характеристиками
            $('#specsSection')[0].scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        });

        $('#hideSpecs').click(function() {
            // Плавное скрытие кнопки "Скрыть характеристики"
            $(this).fadeOut(300, function() {
                // Плавное появление кнопки "Показать характеристики"
                $('#showSpecs').fadeIn(300);
                // Скрываем секцию с характеристиками с анимацией
                $('#specsSection').stop(true, true).slideUp(500).removeClass('show');
            });
        });
    </script>
</body>
</html>
    <!-- Секция "Связаться с нами" -->
    <footer class="contact-section">
        <div class="container py-5">
            <h2 class="text-center mb-4">Связаться с нами</h2>
            <div class="row justify-content-center">
                <div class="col-md-6 text-center">
                    <div class="contact-item">
                        <i class="fas fa-phone-alt contact-icon"></i>
                        <p class="contact-info">Телефон: +7 (XXX) XXX-XX-XX</p>
                    </div>
                    <div class="contact-item mt-4">
                        <i class="fab fa-vk contact-icon"></i>
                        <p class="contact-info">
                            ВКонтакте: 
                            <a href="https://vk.com/respawn_nahabino" 
                               target="_blank" 
                               class="vk-link">
                               vk.com/respawn_nahabino
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Подключение иконок Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <style>
    .contact-section {
        background: linear-gradient(145deg, #1a1a1a, #0d0d0d);
        border-top: 1px solid rgba(255, 40, 40, 0.3);
        color: #fff;
        padding: 20px 0; /* Уменьшенный вертикальный padding */
    }

    .contact-item {
        background: rgba(30, 30, 30, 0.9);
        padding: 15px; /* Уменьшен внутренний отступ */
        border-radius: 15px;
        border: 1px solid #ff4444;
        margin: 10px 0; /* Уменьшен вертикальный отступ */
    }

    .contact-icon {
        font-size: 1.5rem; /* Уменьшен размер иконок */
        color: #ff2d2d;
        margin-bottom: 10px; /* Уменьшен отступ под иконкой */
    }

    .contact-info {
        font-size: 1rem; /* Уменьшен размер текста */
        margin: 0;
    }

    @media (max-width: 768px) {
        .contact-info {
            font-size: 0.9rem; /* Уменьшен размер для мобилок */
        }
        
        .contact-item {
            padding: 10px; /* Меньший padding на мобильных */
        }
        
        .contact-icon {
            font-size: 1.2rem; /* Уменьшенные иконки на мобилках */
        }
    }
</style>
</body>
</html>