// Продукты по категориям
const products = {
    clothing: [
        { id:'product1', name: 'Худи', price: 2500, image: 'https://int.dota2secretshop.com/cdn/shop/files/TI13EventHoodie-Corrected-9-13-24-Front_grande.png?v=1726257343' },
        { id:'product2', name: 'Футболка', price: 1200, image: 'https://int.dota2secretshop.com/cdn/shop/files/IMG-9121_grande.png?v=1724437667' },
        { id:'product3', name: 'Куртка', price: 3000, image: 'https://int.dota2secretshop.com/cdn/shop/files/Ti13_Event_Jacket_-_Front_-_8-21-24_grande.png?v=1724267015' }
    ],
    accessories: [
        { id:'product4', name: 'Коврик для мышки', price: 800, image: 'https://int.dota2secretshop.com/cdn/shop/files/02_large.png?v=1724359333' },
        { id:'product5', name: 'Кейкапы', price: 600, image: 'https://int.dota2secretshop.com/cdn/shop/files/TI13Keycaps-01_grande.jpg?v=1725398843' },
        { id:'product6', name: 'Сумка на плечо', price: 1500, image: 'https://int.dota2secretshop.com/cdn/shop/files/all-over-print-utility-crossbody-bag-white-front-66c79c7bb7228_grande.png?v=1724357776' }
    ],
    toys: [
        { id:'product7', name: 'Игрушка Пудж', price: 2000, image: 'https://int.dota2secretshop.com/cdn/shop/files/pudgeplush-01_grande.jpg?v=1724351445' },
        { id:'product8', name: 'Игрушка Вич Доктор', price: 1800, image: 'https://int.dota2secretshop.com/cdn/shop/files/witchdoctorplush-01_2048x2048.jpg?v=1724351251' }
    ],
    cafes: [
        { id:'product9', name: 'Кружка Dota Cafe', price: 2000, image: 'https://int.dota2secretshop.com/cdn/shop/files/black-glossy-mug-black-15-oz-front-66bfe6a8899a5_2048x2048.png?v=1723852468' },
        { id:'product10', name: 'Коврик Dota Cafe', price: 1800, image: 'https://int.dota2secretshop.com/cdn/shop/files/gaming-mouse-pad-white-36x18-front-66bfdfdaebc97_grande.png?v=1723850722' },
        { id:'product11', name: 'Футболка Dota Cafe', price: 3000, image: 'https://int.dota2secretshop.com/cdn/shop/files/unisex-classic-tee-natural-front-66c543e618697_grande.png?v=1724204023' },
        { id:'product12', name: 'Сумка Dota Cafe', price: 2500, image: 'https://int.dota2secretshop.com/cdn/shop/files/all-over-print-utility-crossbody-bag-white-front-66bfecf5446e6_grande.png?v=1723854090' }
    ]
};



// Инициализация корзины из localStorage или пустой массив
let cart = JSON.parse(localStorage.getItem('cart')) || [];
let totalPrice = cart.reduce((sum, item) => sum + item.price, 0);

// Функция для отображения продуктов по категориям
function displayProducts(categoryId, productList) {
    const categoryElement = document.getElementById(categoryId);
    productList.forEach((product) => {
        categoryElement.innerHTML += `
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card h-100 ${product.id}" >
                <img src="${product.image}" class="card-img-top" alt="${product.name}">
                <div class="card-body">
                    <h5 class="card-title">${product.name}</h5>
                    <p class="card-text">Цена: ${product.price} ₽</p>
                    <button class="btn btn-success" onclick="addToCart('${product.name}', ${product.price}, '${product.image}')">Добавить в корзину</button>
                </div>
            </div>
        </div>`;
    });
}

// Функция для добавления товара в корзину
function addToCart(name, price, image) {
    cart.push({ name, price, image }); // Добавляем изображение в объект товара
    totalPrice += price;
    localStorage.setItem('cart', JSON.stringify(cart));
    updateCartCount();
    alert(`${name} добавлен в корзину`);
}

// Функция обновления счетчика корзины
function updateCartCount() {
    const cartCountElement = document.getElementById('cart-count');
    if (cartCountElement) {
        cartCountElement.innerText = cart.length;
    }
}
// Функция для отображения корзины на странице cart.html
function displayCart() {
    const cartItems = document.getElementById('cart-items');
    cartItems.innerHTML = '';
    if (cart.length === 0) {
        cartItems.innerHTML = '<p>Ваша корзина пуста.</p>';
        document.getElementById('total-price').innerText = '0';
        return;
    }
    cart.forEach((item, index) => {
        cartItems.innerHTML += `
        
        <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
            <div class="card h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <img src="${item.image}" alt="${item.name}" class="img-fluid" style="width: 120px; height: auto; margin-right: 20px;">
                    <div>
                        <h5 class="card-title mb-1">${item.name}</h5>
                        <p class="card-text">Цена: ${item.price} ₽</p>
                    </div>
                    <button class="btn btn-danger" onclick="removeFromCart(${index})">Удалить</button>
                </div>
            </div>
        </div>
        `;
    });
    document.getElementById('total-price').innerText = totalPrice;
}

// Функция для удаления товара из корзины
function removeFromCart(index) {
    totalPrice -= cart[index].price;
    cart.splice(index, 1);
    localStorage.setItem('cart', JSON.stringify(cart));
    updateCartCount();
    displayCart();
}

// Функция для перехода к оформлению заказа
function proceedToCheckout() {
    $('#checkoutModal').modal('show');
}

// Функция для подтверждения заказа
function submitOrder() {
    const fullname = document.getElementById('fullname').value.trim();
    const address = document.getElementById('address').value.trim();
    const postalCode = document.getElementById('postal-code').value.trim();
    const deliveryDate = document.getElementById('delivery-date').value;

    console.log('Проверяем данные формы');
    console.log('ФИО:', fullname, 'Адрес:', address, 'Индекс:', postalCode, 'Дата доставки:', deliveryDate);

    // Проверяем, что все поля заполнены
    if (fullname && address && postalCode && deliveryDate) {
        // Отображаем сообщение с данными заказа
        alert(`Заказ оформлен на имя ${fullname}. Товары будут доставлены по адресу: ${address} к дате: ${deliveryDate}`);
        
        // Очищаем корзину
        cart = [];
        totalPrice = 0;
        localStorage.removeItem('cart'); // Удаляем корзину из localStorage
        
        // Обновляем отображение корзины
        displayCart();
        updateCartCount();
        
        console.log('Закрываем модальное окно');
        // Закрываем модальное окно через API Bootstrap
        $('#checkoutModal').modal('hide'); // Закрытие модального окна

    } else {
        alert('Пожалуйста, заполните все поля.');
    }
}



// Инициализация страницы при загрузке
document.addEventListener('DOMContentLoaded', function () {
    // Отображение продуктов только на главной странице
    if (document.getElementById('clothing-list')) {
        displayProducts('clothing-list', products.clothing);
        displayProducts('accessories-list', products.accessories);
        displayProducts('toys-list', products.toys);
        displayProducts('cafes-list', products.cafes);
    }

    // Отображение корзины только на странице cart.html
    if (document.getElementById('cart-items')) {
        displayCart();
    }

    // Обновляем счетчик корзины на всех страницах
    updateCartCount();
});
document.addEventListener('DOMContentLoaded', function() {
    let isUserIdentified = false; // Флаг для отслеживания состояния пользователя

    // Открытие чата
    document.getElementById('openChatBtn').addEventListener('click', function() {
        document.getElementById('chatModal').style.display = 'block';
        this.style.display = 'none'; // Скрываем кнопку открытия чата
    });

    // Закрытие чата
    document.getElementById('closeChatBtn').addEventListener('click', function() {
        document.getElementById('chatModal').style.display = 'none';
        document.getElementById('openChatBtn').style.display = 'block'; // Показываем кнопку открытия чата
    });

    // Отправка сообщения
    document.getElementById('sendMessageBtn').addEventListener('click', function() {
        const nameInput = document.getElementById('chat-user-name');
        const emailInput = document.getElementById('chat-user-email');
        const messageInput = document.getElementById('chat-input');

        const name = nameInput.value.trim();
        const email = emailInput.value.trim();
        const messageText = messageInput.value.trim();

        if (!isUserIdentified) {
            // Проверяем, заполнены ли имя и email
            if (name && email) {
                isUserIdentified = true; // Устанавливаем флаг, что пользователь идентифицирован
                nameInput.parentElement.style.display = 'none'; // Скрываем поле имени
                emailInput.parentElement.style.display = 'none'; // Скрываем поле email
                messageInput.focus(); // Устанавливаем фокус на поле сообщения
                return; // Завершаем функцию, чтобы не отправлять сообщение
            } else {
                alert('Пожалуйста, заполните имя и email!');
                return; // Завершаем функцию, если поля не заполнены
            }
        }

        // Проверяем, заполнено ли сообщение
        if (messageText) {
            // Создаем новое сообщение пользователя
            const messageElement = document.createElement('div');
            messageElement.classList.add('chat-message', 'user');
            messageElement.textContent = `${name} (${email}): ${messageText}`;

            // Добавляем сообщение в чат
            document.getElementById('chat-messages').appendChild(messageElement);

            // Очищаем поле ввода сообщения
            messageInput.value = '';

            // Прокрутка вниз для отображения нового сообщения
            const chatMessages = document.getElementById('chat-messages');
            chatMessages.scrollTop = chatMessages.scrollHeight;

            // Автоответ менеджера
            setTimeout(() => {
                const responseElement = document.createElement('div');
                responseElement.classList.add('chat-message', 'manager');
                responseElement.textContent = 'Спасибо за ваше сообщение! Оператор сейчас занят. Мы приняли ваше сообщение и свяжемся с вами в ближайшее время.'; // Менеджер отвечает

                // Добавляем ответ в чат
                chatMessages.appendChild(responseElement);

                // Прокрутка вниз для отображения нового ответа
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }, 1000); // Задержка в 1 секунду перед ответом
        } else {
            alert('Пожалуйста, введите сообщение!');
        }
    });
});
