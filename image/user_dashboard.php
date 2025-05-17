<?php
session_start();
require 'includes/db.php';
date_default_timezone_set('Europe/Moscow');

// Запрет кэширования
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Обработка POST-запросов
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Бронирование
    if (isset($_POST['book'])) {
        $pc_id = $_POST['pc_id'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        
        try {
            $pdo->beginTransaction();

            // Проверка данных
            if (empty($pc_id) || empty($start_time) || empty($end_time)) {
                throw new Exception("Не все данные заполнены");
            }

            // Получение информации о ПК
            $pc_stmt = $pdo->prepare("SELECT name FROM pcs WHERE id = ?");
            $pc_stmt->execute([$pc_id]);
            $pc = $pc_stmt->fetch();
            
            if (!$pc) {
                throw new Exception("Компьютер не найден");
            }

            // Валидация времени
            $start = new DateTime($start_time, new DateTimeZone('Europe/Moscow'));
            $end = new DateTime($end_time, new DateTimeZone('Europe/Moscow'));
            $now = new DateTime('now', new DateTimeZone('Europe/Moscow'));

            $interval = $start->diff($end);
            $hours = $interval->h + ($interval->days * 24);
            
            if ($hours < 1 || $hours > 12) {
                throw new Exception("Можно бронировать от 1 до 12 часов");
            }

            // Проверка наложения броней
            $check_stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM bookings 
                WHERE pc_id = ? 
                AND (
                    (start_time < ? AND end_time > ?) OR
                    (start_time >= ? AND start_time < ?)
                )
            ");
            $start = new DateTime($start_time, new DateTimeZone('Europe/Moscow'));
$end = new DateTime($end_time, new DateTimeZone('Europe/Moscow'));

$start_utc = clone $start;
$start_utc->setTimezone(new DateTimeZone('UTC'));
$end_utc = clone $end;
$end_utc->setTimezone(new DateTimeZone('UTC'));

// Затем выполните проверку:
$check_stmt->execute([
    $pc_id, 
    $end_utc->format('Y-m-d H:i:s'),
    $start_utc->format('Y-m-d H:i:s'),
    $start_utc->format('Y-m-d H:i:s'),
    $end_utc->format('Y-m-d H:i:s')
]);
            
            if ($check_stmt->fetchColumn() > 0) {
                throw new Exception("Выбранное время занято");
            }

            // Списание средств
            $cost = $hours * 130;
            if ($user['balance'] < $cost) {
                throw new Exception("Недостаточно средств");
            }

            // Создание брони
            $start_utc = clone $start;
            $start_utc->setTimezone(new DateTimeZone('UTC'));
            $end_utc = clone $end;
            $end_utc->setTimezone(new DateTimeZone('UTC'));
            $pdo->prepare("
    INSERT INTO bookings (user_id, pc_id, start_time, end_time, cost)
    VALUES (?, ?, ?, ?, ?)
")->execute([
    $user_id, 
    $pc_id, 
    $start_utc->format('Y-m-d H:i:s'),
    $end_utc->format('Y-m-d H:i:s'),
    $cost
]);

            $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")
                ->execute([$cost, $user_id]);

            $_SESSION['success'] = "ПК {$pc['name']} забронирован! Стоимость: $cost ₽";
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = $e->getMessage();
        }
        header("Location: user_dashboard.php");
        exit();
    }

    // Отмена бронирования
    if (isset($_POST['cancel_booking'])) {
        $booking_id = $_POST['booking_id'];
        try {
            $pdo->beginTransaction();

            $booking_stmt = $pdo->prepare("
                SELECT * FROM bookings 
                WHERE id = ? 
                AND user_id = ?
                AND end_time > NOW()
            ");
            $booking_stmt->execute([$booking_id, $user_id]);
            $booking = $booking_stmt->fetch();

            if ($booking) {
                $refund = $booking['cost'] * 0.5;
                
                $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")
                    ->execute([$refund, $user_id]);

                $pdo->prepare("DELETE FROM bookings WHERE id = ?")->execute([$booking_id]);

                $_SESSION['success'] = "Бронь отменена. Возвращено: $refund ₽";
            } else {
                throw new Exception("Невозможно отменить завершенную бронь");
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = $e->getMessage();
        }
        header("Location: user_dashboard.php");
        exit();
    }

    // Покупка товара
    if (isset($_POST['buy_product'])) {
        $product_id = $_POST['product_id'];
        try {
            $pdo->beginTransaction();

            $product_stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $product_stmt->execute([$product_id]);
            $product = $product_stmt->fetch();

            if ($product && $product['quantity'] > 0 && $user['balance'] >= $product['price']) {
                $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")
                    ->execute([$product['price'], $user_id]);

                $pdo->prepare("UPDATE products SET quantity = quantity - 1 WHERE id = ?")
                    ->execute([$product_id]);

                $_SESSION['success'] = "Товар '{$product['name']}' куплен!";
            } else {
                $_SESSION['error'] = "Недостаточно средств или товара нет в наличии";
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Ошибка: " . $e->getMessage();
        }
        header("Location: user_dashboard.php#shop");
        exit();
    }
}

// Получение данных
$pcs = $pdo->query("SELECT * FROM pcs")->fetchAll(PDO::FETCH_ASSOC);
$products = $pdo->query("SELECT * FROM products")->fetchAll(PDO::FETCH_ASSOC);

// Все активные бронирования (для отображения занятых слотов)
$active_bookings_all = $pdo->query("
    SELECT b.*, p.name as pc_name,
           CONVERT_TZ(b.start_time, '+00:00', '+03:00') as start_time_local,
           CONVERT_TZ(b.end_time, '+00:00', '+03:00') as end_time_local
    FROM bookings b
    JOIN pcs p ON b.pc_id = p.id 
    WHERE b.end_time > UTC_TIMESTAMP()
    ORDER BY b.start_time DESC
")->fetchAll(PDO::FETCH_ASSOC);

// История бронирований пользователя
$all_bookings = $pdo->prepare("
    SELECT 
        b.*,
        p.name as pc_name,
        CONVERT_TZ(b.start_time, '+00:00', '+03:00') as start_time,
        CONVERT_TZ(b.end_time, '+00:00', '+03:00') as end_time
    FROM bookings b
    LEFT JOIN pcs p ON b.pc_id = p.id 
    WHERE b.user_id = ? 
    ORDER BY b.start_time DESC
");
$all_bookings->execute([$user_id]);
$all_bookings = $all_bookings->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Личный кабинет</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
body {
    background-color: #0a0a0a;
    color: #ffffff;
    font-family: 'Roboto', sans-serif;
    margin: 0;
    padding: 0;
    line-height: 1.6;
}

.container {
    max-width: 1200px;
    margin: 100px auto 0;
    padding: 20px;
}

/* Навигация */
.navbar {
    height: 60px; /* Фиксированная высота */
    padding: 8px 20px; /* Уменьшенные отступы */
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
}

.container {
    margin-top: 80px; /* Отступ под навигацией */
    padding: 20px 15px; /* Безопасные боковые отступы */
}

.navbar-brand {
    font-size: 1.4rem; /* Уменьшенный размер логотипа */
    letter-spacing: 1px;
}

.user-info {
    font-size: 0.8rem; /* Уменьшенный размер текста */
    gap: 8px;
}

.cta-button {
    padding: 8px 15px;
    font-size: 0.9rem;
}

/* Для мобильных устройств */
@media (max-width: 768px) {
    .navbar {
        height: 50px;
        padding: 5px 10px;
    }
    
    .navbar-brand {
        font-size: 1.2rem;
    }
    
    .container {
        margin-top: 70px;
    }
    
    .user-info span {
        display: none; /* Скрываем лишнюю информацию */
    }
}
.navbar {
    background: linear-gradient(145deg, #1a1a1a, #0d0d0d);
    border-bottom: 1px solid rgba(255, 40, 40, 0.3);
    padding: 12px 30px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    position: fixed;
    width: 100%;
    
    top: 0;
    z-index: 1000;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.navbar-brand {
    font-family: 'Orbitron', sans-serif;
    color: #ff2d2d !important;
    font-size: 1.8rem;
    letter-spacing: 1.5px;
    transition: all 0.3s ease;
    order: 1;
    margin-right: auto;
}

.navbar-links {
    display: flex;
    align-items: center;
    gap: 25px;
}
.logout-btn {
    order: 4;
    margin-left: 30px;
    margin-right: 30px;
}
.user-info {
    display: flex;
    gap: 15px;
    align-items: center;
    font-size: 0.9rem;
}
.user-block {
    order: 2;
    display: flex;
    align-items: center;
    gap: 25px;
    margin-left: auto;
}

.balance {
    color: #4CAF50 !important;
    font-weight: bold;
}

.cta-button {
    
    padding: 10px 25px;
    background: linear-gradient(135deg, #ff2d2d, #cc2424);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.cta-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255, 45, 45, 0.4);
}

/* Вкладки */
.tabs {
    display: flex;
    gap: 15px;
    margin: 30px 0;
    border-bottom: 2px solid #ff4444;
}

.tab-button {
    background: none;
    border: none;
    color: #fff;
    padding: 12px 25px;
    cursor: pointer;
    font-family: 'Orbitron', sans-serif;
    transition: all 0.3s ease;
}

.tab-button.active {
    border-bottom: 3px solid #ff4444;
}

/* Карточки ПК */
.pc-card {
    background: linear-gradient(145deg, #1a1a1a, #131313);
    border-radius: 15px;
    padding: 25px;
    margin: 20px 0;
    border: 1px solid rgba(255, 45, 45, 0.2);
    transition: transform 0.3s ease;
}

.pc-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(255, 45, 45, 0.2);
}

.time-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    gap: 8px;
    margin-top: 15px;
}

.time-slot {
    padding: 10px;
    border: 1px solid #333;
    border-radius: 5px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.time-slot.selected {
    background: #4CAF50 !important;
    color: white;
    border-color: #4CAF50;
}

.time-slot.booked {
    background: #ff4444;
    cursor: not-allowed;
    opacity: 0.7;
}

.time-slot.past {
    background: #666;
    cursor: not-allowed;
    opacity: 0.5;
}

/* Магазин */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 25px;
    padding: 20px 0;
}

.product-card {
    background: rgba(30, 30, 30, 0.9);
    border-radius: 10px;
    padding: 20px;
    border: 1px solid #ff4444;
    transition: transform 0.3s ease;
}

.product-card:hover {
    transform: translateY(-5px);
}

.product-image img {
    width: 100%;
    height: 200px;
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 15px;
}

.product-out-of-stock {
    position: relative;
    opacity: 0.5;
}

.product-out-of-stock::after {
    content: "Нет в наличии";
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: #ff4444;
    font-weight: bold;
}

/* История бронирований */
.booking-table {
    width: 100%;
    border-collapse: collapse;
    background: rgba(40, 40, 40, 0.9);
    margin: 20px 0;
}

.booking-table th {
    background: #ff4444;
    padding: 15px;
    text-align: left;
}

.booking-table td {
    padding: 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.cancel-btn {
    background: #ff4444 !important;
    padding: 8px 15px;
    font-size: 0.9rem;
}

/* Адаптивность */
@media (max-width: 768px) {
    .navbar {
        padding: 10px 15px;
        flex-direction: column;
        gap: 15px;
    }

    .time-grid {
        grid-template-columns: repeat(6, 1fr);
    }

    .product-card {
        width: 100%;
    }

    .tabs {
        flex-wrap: wrap;
    }
}

@media (max-width: 480px) {
    .pc-card {
        padding: 15px;
    }

    .time-slot {
        padding: 8px;
        font-size: 0.9rem;
    }

    .products-grid {
        grid-template-columns: 1fr;
    }
}

/* Дополнительные элементы */
.selected-slot-info {
    background: rgba(30, 30, 30, 0.9);
    border: 1px solid #ff4444;
    padding: 20px;
    margin: 20px 0;
    border-radius: 10px;
}

.date-picker {
    margin: 20px 0;
}

#booking-date {
    background: #333;
    color: #fff;
    border: 1px solid #ff4444;
    padding: 8px;
    border-radius: 5px;
    font-family: 'Orbitron', sans-serif;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin: 20px 0;
}

.alert.success {
    background: rgba(76, 175, 80, 0.1);
    border: 1px solid #4CAF50;
}

.alert.error {
    background: rgba(255, 68, 68, 0.1);
    border: 1px solid #ff4444;
}
    </style>
</head>
<div>

<nav class="navbar">
    <a class="navbar-brand" href="index.php"><img src="image/logo.jpg" alt="Logo">RESPAWN</a>
        <div class="user-block">
            <div class="user-info">
                <span>ID: <?= $user_id ?></span>
                <span><?= htmlspecialchars($user['username']) ?></span>
                <span class="balance"><?= number_format($user['balance'], 2) ?> ₽</span>
            </div>
            <a href="logout.php" class="cta-button logout-btn">Выход</a>
        </div>
    </nav>

    <div class="container">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert error"><?= $_SESSION['error'] ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert success"><?= $_SESSION['success'] ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        

            <div class="selected-slot-info" id="booking-info" style="display: none;">
                <div class="booking-details">
                    Выбрано: <span id="selected-pc-name"></span><br>
                    Время: с <span id="selected-start-time"></span> до <span id="selected-end-time"></span><br>
                    Стоимость: <span id="selected-cost"></span> ₽
                </div>
                <form method="POST">
                    <input type="hidden" name="pc_id" id="selected-pc">
                    <input type="hidden" name="start_time" id="selected-start">
                    <input type="hidden" name="end_time" id="selected-end">
                    <button type="submit" name="book" class="cta-button">Подтвердить бронирование</button>
                </form>
            </div>

            <?php foreach ($pcs as $pc): ?>
    <div class="pc-card" data-pc-id="<?= $pc['id'] ?>">
        <h3><?= htmlspecialchars($pc['id']) ?> - <?= htmlspecialchars($pc['name']) ?></h3>
        <div class="time-grid">
            <?php
            $now = new DateTime('now', new DateTimeZone('Europe/Moscow'));
            $start = (clone $now)->setTime((int)$now->format('H'), 0);
            $end = (clone $start)->modify('+24 hours');
            $interval = new DateInterval('PT1H');
            $period = new DatePeriod($start, $interval, $end);

            foreach ($period as $slotTime):
                $isBooked = false;
                foreach ($active_bookings_all as $booking) {
                    if ($booking['pc_id'] != $pc['id']) continue;

                    $bookingStart = new DateTime($booking['start_time_local'], new DateTimeZone('Europe/Moscow'));
                    $bookingEnd = new DateTime($booking['end_time_local'], new DateTimeZone('Europe/Moscow'));

                    if ($slotTime >= $bookingStart && $slotTime < $bookingEnd) {
                        $isBooked = true;
                        break;
                    }
                }
                
                $isPast = $slotTime < $start;
            ?>
                <div class="time-slot 
                    <?= $isBooked ? 'booked' : '' ?> 
                    <?= $isPast ? 'past' : '' ?>"
                    data-time="<?= $slotTime->format('Y-m-d H:i:s') ?>">
                    <?= $slotTime->format('H:i') ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
        </div>
        <div class="container">
        <div id="shop" class="tab-content">
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card <?= $product['quantity'] <= 0 ? 'product-out-of-stock' : '' ?>">
                        <div class="product-image">
                            <img src="images/products/<?= htmlspecialchars($product['image'] ?? 'default.jpg') ?>" 
                                alt="<?= htmlspecialchars($product['name']) ?>">
                        </div>
                        <h3><?= htmlspecialchars($product['name']) ?></h3>
                        <p><?= htmlspecialchars($product['description']) ?></p>
                        <p class="price">Цена: <?= number_format($product['price'], 2) ?> ₽</p>
                        <p>Осталось: <?= $product['quantity'] ?></p>
                        <?php if ($product['quantity'] > 0): ?>
                            <form method="POST">
                                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                <button type="submit" name="buy_product" class="cta-button buy-btn">Купить</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        </div>
        <div class="container">                   
        <div id="history" class="tab-content">
            <table class="booking-table">
                <thead>
                    <tr>
                        <th>ПК</th>
                        <th>Начало</th>
                        <th>Окончание</th>
                        <th>Стоимость</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_bookings as $booking): ?>
                        <tr>
                            <td><?= htmlspecialchars($booking['pc_name'] ?? 'Удаленный ПК') ?></td>
                            <td><?= date('d.m.Y H:i', strtotime($booking['start_time'])) ?></td>
                            <td><?= date('d.m.Y H:i', strtotime($booking['end_time'])) ?></td>

                            <td><?= number_format($booking['cost'], 2) ?> ₽</td>
                            <td>
                                <?php if (strtotime($booking['end_time']) > time()): ?>
                                    <form method="POST">
                                        <input type="hidden" name="booking_id" 
                                            value="<?= htmlspecialchars($booking['id']) ?>">
                                        <button type="submit" name="cancel_booking" class="cta-button cancel-btn">
                                            Отменить (-50%)
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    </div> 

    <script>
    document.addEventListener('DOMContentLoaded', function() {
    // Переключение вкладок
    document.querySelectorAll('.tab-button').forEach(button => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            document.getElementById(button.dataset.tab).classList.add('active');
        });
    });

    // Выбор времени
    let selectedSlots = [];
    const maxHours = 12;
    const hourPrice = 130;

    function updateBookingInfo() {
        const infoPanel = document.getElementById('booking-info');
        if (selectedSlots.length === 0) {
            infoPanel.style.display = 'none';
            return;
        }

        const pcCard = document.querySelector('.pc-card');
        const pcName = pcCard ? pcCard.querySelector('h3').textContent : 'Неизвестный ПК';
        const startTime = new Date(selectedSlots[0].time + 'Z');
        const endTime = new Date(selectedSlots[selectedSlots.length - 1].time + 'Z');
        endTime.setHours(endTime.getHours() + 1);

        const hours = selectedSlots.length;
        const cost = hours * hourPrice;

        document.getElementById('selected-pc-name').textContent = pcName;
        document.getElementById('selected-start-time').textContent = startTime.toLocaleString('ru-RU', {
            hour: '2-digit',
            minute: '2-digit',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        }).replace(',', '');
        document.getElementById('selected-end-time').textContent = endTime.toLocaleString('ru-RU', {
            hour: '2-digit',
            minute: '2-digit',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        }).replace(',', '');
        document.getElementById('selected-cost').textContent = cost;

        // Убедимся, что время передается в формате ISO с учетом часового пояса
        document.getElementById('selected-pc').value = selectedSlots[0].pcId;
        document.getElementById('selected-start').value = startTime.toISOString().slice(0, 16);
        document.getElementById('selected-end').value = endTime.toISOString().slice(0, 16);

        infoPanel.style.display = 'block';
    }

    document.querySelectorAll('.time-slot:not(.booked):not(.past)').forEach(slot => {
        slot.addEventListener('click', () => {
            const pcId = slot.closest('.pc-card').dataset.pcId;
            const time = slot.dataset.time;
            const index = selectedSlots.findIndex(item => item.pcId === pcId && item.time === time);

            if (index === -1) {
                // Проверка на выбор разных ПК
                if (selectedSlots.length > 0 && selectedSlots[0].pcId !== pcId) {
                    alert('Выберите время на одном компьютере');
                    return;
                }
                
                // Проверка последовательности времени
                const newTime = new Date(time);
                if (selectedSlots.length > 0) {
                    const lastTime = new Date(selectedSlots[selectedSlots.length - 1].time);
                    const diff = (newTime - lastTime) / (1000 * 60 * 60);
                    if (diff !== 1) {
                        alert('Выберите последовательные часы');
                        return;
                    }
                }
                
                selectedSlots.push({ pcId, time });
                slot.classList.add('selected');
            } else {
                selectedSlots.splice(index, 1);
                slot.classList.remove('selected');
            }

            updateBookingInfo();
        });
    });
});
    </script>
</body>
</html>