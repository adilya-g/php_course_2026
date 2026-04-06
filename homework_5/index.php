<?php

require_once 'Product.php';
require_once 'Cart.php';

session_start();

//Возвращает файлы напрямую
if (preg_match('/\.(?:png|jpg|jpeg|gif|css|html?|js)$/', $_SERVER["REQUEST_URI"])) {
    return false;
}


$products = [
    1 => new Product(1, 'Ноутбук Lenovo', 50000),
    2 => new Product(2, 'Мышь Logitech', 1500),
    3 => new Product(3, 'Клавиатура Mech', 3500),
    4 => new Product(4, 'Монитор Samsung', 25000),
    5 => new Product(5, 'Наушники Sony', 8000),
];

$routes = [
    ['method' => 'GET', 'path' => '/', 'handler' => 'indexPage'],
    ['method' => 'GET', 'path' => '/add', 'handler' => 'addToCart'],
    ['method' => 'GET', 'path' => '/cart', 'handler' => 'showCart'],
    ['method' => 'GET', 'path' => '/remove', 'handler' => 'removeFromCart'],
    ['method' => 'GET', 'path' => '/clear', 'handler' => 'clearCart'],
];

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

$routeFound = false;
foreach ($routes as $route) {
    if ($route['method'] === $method && $route['path'] === $uri) {
        $routeFound = true;
        if ($route['handler'] === 'indexPage' || $route['handler'] === 'addToCart') {
            call_user_func($route['handler'], $products);
        } else {
            call_user_func($route['handler']);
        }
        break;
    }
}

if (!$routeFound) {
    http_response_code(404);
    echo '404 - Страница не найдена';
}


function indexPage($products) {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>Магазин</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <h1>Каталог товаров</h1>
        <div class="products">
            <?php foreach ($products as $product): ?>
                <div class="product">
                    <h3><?php echo htmlspecialchars($product->getTitle()); ?></h3>
                    <div class="price"><?php echo number_format($product->getPrice(), 0, '', ' '); ?> ₽</div>
                    <a href="/add?id=<?php echo $product->getId(); ?>" class="btn">Добавить в корзину</a>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="cart-link">
            <a href="/cart" class="btn">Перейти в корзину</a>
        </div>
    </body>
    </html>
    <?php
}

function addToCart($products) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!isset($products[$id])) {
        http_response_code(404);
        echo 'Товар не найден';
        return;
    }
    
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = new Cart();
    }
    
    $_SESSION['cart']->add($products[$id]);
    
    header('Location: /');
    exit();
}

function showCart() {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = new Cart();
    }
    
    $cart = $_SESSION['cart'];
    $items = $cart->getItems();
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>Корзина</title>
        <link rel="stylesheet" href="/style.css">
    </head>
    <body>
        <h1>Корзина</h1>
        <?php if (empty($items)): ?>
            <div class="empty">
                <p>Корзина пуста</p>
                <a href="/index" class="continue-btn">Продолжить покупки</a>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Товар</th>
                        <th>Цена за ед.</th>
                        <th>Количество</th>
                        <th>Стоимость</th>
                        <th>Действие</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['product']->getTitle()); ?></td>
                            <td><?php echo number_format($item['product']->getPrice(), 0, '', ' '); ?> ₽</td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><?php echo number_format($item['product']->getPrice() * $item['quantity'], 0, '', ' '); ?> ₽</td>
                            <td><a href="/remove?id=<?php echo $item['product']->getId(); ?>" class="remove-btn">Удалить</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="total">
                Общая стоимость: <?php echo number_format($cart->getTotal(), 0, '', ' '); ?> ₽
            </div>
                        <div class="total">
                Всего товаров: <?php echo number_format($cart->getTotalQuantity(), 0, '', ' '); ?> шт
            </div>
            <div class="actions">
                <a href="/clear" class="clear-btn" onclick="return confirm('Очистить корзину?')">Очистить корзину</a>
                <a href="/" class="continue-btn">Продолжить покупки</a>
            </div>
        <?php endif; ?>
    </body>
    </html>
    <?php
}

function removeFromCart() {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = new Cart();
    }
    
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $_SESSION['cart']->remove($id);
    
    header('Location: /cart');
    exit();
}

function clearCart() {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = new Cart();
    }
    
    $_SESSION['cart']->clear();
    
    header('Location: /cart');
    exit();
}