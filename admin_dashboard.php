<?php
session_start();
require 'includes/db.php';
require 'includes/functions.php'; // Подключите файл с функцией logAction()

// Проверка прав администратора
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: auth.php");
    exit();
}

// Обработка действий администратора
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Изменение баланса
        if (isset($_POST['update_balance'])) {
            $user_id = (int)$_POST['user_id'];
            $amount = (float)$_POST['amount'];
            
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$amount, $user_id]);
            logAction('balance_update', $user_id, "Изменение баланса на $amount ₽");
            $_SESSION['success'] = "Баланс успешно изменён";
        }

        // Добавление продукта
        if (isset($_POST['add_product'])) {
            $name = trim($_POST['name']);
            $price = (float)$_POST['price'];
            $quantity = (int)$_POST['quantity'];
            $description = trim($_POST['description']);
            
            $stmt = $pdo->prepare("INSERT INTO products (name, price, quantity, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $price, $quantity, $description]);
            $product_id = $pdo->lastInsertId();
            logAction('product_add', $product_id, "Добавлен товар: $name");
            $_SESSION['success'] = "Товар $name успешно добавлен";
        }

        // Удаление товара
        if (isset($_POST['delete_product'])) {
            $product_id = $_POST['product_id'];
            
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            logAction('product_delete', $product_id, "Удален товар ID: $product_id");
            $_SESSION['success'] = "Товар успешно удален";
        }

        // Отмена бронирования
        if (isset($_POST['cancel_booking'])) {
            $booking_id = $_POST['booking_id'];
            $refund_percent = (int)$_POST['refund_percent'];
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch();
            
            if ($booking) {
                $refund = $booking['cost'] * ($refund_percent / 100);
                
                $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")
                    ->execute([$refund, $booking['user_id']]);
                
                $pdo->prepare("DELETE FROM bookings WHERE id = ?")->execute([$booking_id]);
                logAction('booking_cancel', $booking_id, "Отмена бронирования, возвращено $refund ₽");
                $_SESSION['success'] = "Бронь отменена. Возвращено: $refund ₽ ($refund_percent%)";
            }
            
            $pdo->commit();
        }
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "Ошибка базы данных: " . $e->getMessage();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Получение данных (один запрос для каждого типа данных)
try {
    $users = $pdo->query("SELECT id, username, balance FROM users")->fetchAll(PDO::FETCH_ASSOC);
    
    $bookings = $pdo->query("
        SELECT 
            b.*, 
            u.username, 
            p.name AS pc_name,
            CONVERT_TZ(b.start_time, '+00:00', '+03:00') AS start_time_local,
            CONVERT_TZ(b.end_time, '+00:00', '+03:00') AS end_time_local
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN pcs p ON b.pc_id = p.id
        ORDER BY b.start_time DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $products = $pdo->query("SELECT * FROM products")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка получения данных: " . $e->getMessage());
}

$error = $_SESSION['error'] ?? null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/auth.css">
</head>
<style>
        /* Дополнительные стили для админки */
        .admin-section {
            margin: 30px 0;
            padding: 30px;
            background: rgba(30, 30, 30, 0.9);
            border-radius: 15px;
            border: 1px solid #ff4444;
        }
        .delete-btn {
        background: #ff4444 !important;
        margin-top: 10px;
    }
        .pc-card{
            padding: 60px;
            margin: 30px;
            margin-top: 50px;
        }
        .admin-section h3 {
            color: #ff4444;
            margin-bottom: 20px;
            font-family: 'Orbitron', sans-serif;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .booking-table {
    margin-top: 20px;
    width: 100%;
}

.booking-table td, .booking-table th {
    padding: 12px;
    text-align: left;
}
.container {
    margin-top: 80px; /* Новое свойство */
    padding: 20px;
}

/* Выравниваем кнопку выхода вправо */
.user-block {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 20px;
}
.logout-btn {
    order: 4;
    margin-left: 80%;
    
   
}
.styled-select {
    width: 100%;
    padding: 12px 40px 12px 15px;
    background: rgba(40, 40, 40, 0.9);
    border: 1px solid #ff4444;
    border-radius: 8px;
    color: #fff;
    appearance: none;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
}
/* Адаптивность для таблицы */
@media (max-width: 768px) {
    .booking-table {
        display: block;
        overflow-x: auto;
    }
}
.navbar{
    height: 125px;
}
/* Стили для textarea в админке */

    </style>
<body>
<nav class="navbar">
    <a class="navbar-brand" href="index.php">
        <img src="image/logo.jpg" alt="Logo">RESPAWN ADMIN
    </a>
    <div class="user-block">
        <!-- Добавим обертку для элементов справа -->
        <div class="user-info">
            <span class="balance">Администратор</span>
        </div>
        <a href="logout.php" class="cta-button logout-btn">Выход</a>
    </div>
</nav>
        
    <div class="container">
        <?php if ($error): ?>
            <div class="alert error"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert success"><?= $success ?></div>
        <?php endif; ?>

        <!-- Секция управления балансом -->
 <div class="pc-card">
    <h3>💰 Управление балансом</h3>
    <form method="POST" class="admin-form">
        <div class="custom-select">
            <select name="user_id" required class="styled-select">
                <option value="" disabled selected>Выберите пользователя...</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= $user['id'] ?>">
                        🧑💻 <?= htmlspecialchars($user['username']) ?> 
                        <span class="balance-indicator">(<?= $user['balance'] ?>₽)</span>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="select-arrow"></div>
        </div>
        
        <div class="amount-input">
            <input type="number" step="0.01" name="amount" 
                   placeholder="Сумма изменения" required class="styled-input">
            
        </div>
        
        <button type="submit" name="update_balance" class="cta-button balance-btn">
            <span class="icon">🔄</span> Обновить баланс
        </button>
    </form>
</div>

        <!-- Секция управления товарами -->
        <div class="pc-card">
            <h3>Управление товарами</h3>
            <form method="POST">
                <input type="text" name="name" placeholder="Название" required>
                <input type="number" step="0.01" name="price" placeholder="Цена" required>
                <input type="number" name="quantity" placeholder="Количество" required>
                <textarea 
                    name="description" 
                    placeholder="Описание"
                    class="product-description"
                    ></textarea>
                <button type="submit" name="add_product" class="cta-button">Добавить товар</button>
            </form>

            <div class="products-grid">
            <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <h4><?= htmlspecialchars($product['name']) ?></h4>
                    <p>Цена: <?= $product['price'] ?> ₽</p>
                    <p>Осталось: <?= $product['quantity'] ?></p>
                    <form method="POST">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        <button type="submit" name="delete_product" class="cta-button delete-btn">Удалить</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
        </div>

        <!-- Секция бронирований -->
        <div class="pc-card">
            <h3>Активные бронирования</h3>
            <table class="booking-table">
                <thead>
                    <tr>
                        <th>Пользователь</th>
                        <th>ПК</th>
                        <th>Начало</th>
                        <th>Окончание</th>
                        <th>Стоимость</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><?= htmlspecialchars($booking['username']) ?></td>
                            <td><?= htmlspecialchars($booking['pc_name']) ?></td>
                            <td><?= date('d.m.Y H:i', strtotime($booking['start_time'])) ?></td>
                            <td><?= date('d.m.Y H:i', strtotime($booking['end_time'])) ?></td>
                            <td><?= $booking['cost'] ?> ₽</td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                    <select name="refund_percent" required>
                                        <option value="0">0% возврата</option>
                                        <option value="50">50% возврата</option>
                                        <option value="100">100% возврата</option>
                                    </select>
                                    <button type="submit" name="cancel_booking" class="cta-button cancel-btn">Отменить</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="pc-card">
    <h3>📜 История действий</h3>
    
    <div class="log-filters">
        <select id="logTypeFilter" class="styled-select">
            <option value="">Все действия</option>
            <option value="balance_update">Изменения баланса</option>
            <option value="product_add">Добавление товаров</option>
            <option value="product_delete">Удаление товаров</option>
        </select>
    </div>

    <table class="booking-table">
        <thead>
            <tr>
                <th>Дата</th>
                <th>Действие</th>
                <th>Инициатор</th>
                <th>Объект</th>
                <th>Детали</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $logs = $pdo->query("
                SELECT l.*, u.username 
                FROM logs l
                LEFT JOIN users u ON l.user_id = u.id
                ORDER BY l.created_at DESC
                LIMIT 100
            ")->fetchAll();
            
            foreach ($logs as $log):
            ?>
            <tr>
                <td><?= date('d.m.Y H:i', strtotime($log['created_at'])) ?></td>
                <td><?= matchActionType($log['action_type']) ?></td>
                <td><?= $log['username'] ?> (ID: <?= $log['user_id'] ?>)</td>
                <td>ID <?= $log['target_id'] ?></td>
                <td><?= htmlspecialchars($log['details']) ?></td>
                <td><?= $log['ip_address'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
function matchActionType($type) {
    return match($type) {
        'balance_update' => '🔄 Изменение баланса',
        'product_add' => '➕ Добавление товара',
        'product_delete' => '❌ Удаление товара',
        default => $type
    };
}
?>
    </div>
</body>
</html>