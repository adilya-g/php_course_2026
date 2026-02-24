<?php

$products = [
    [
        'id' => 1,
        'name' => 'Смартфон XYZ',
        'price' => 29999.99,
        'tags' => ['электроника', 'смартфон', 'распродажа']
    ],
    [
        'id' => 2,
        'name' => 'Ноутбук ProBook',
        'price' => 54999.90,
        'tags' => ['электроника', 'ноутбук', 'новинка']
    ],
    [
        'id' => 3,
        'name' => 'Наушники SoundMax',
        'price' => 3499.50,
        'tags' => ['электроника', 'аксессуары', 'аудио']
    ],
    [
        'id' => 4,
        'name' => 'Фитнес-браслет Active',
        'price' => 2499.00,
        'tags' => ['электроника', 'спорт', 'гаджеты']
    ],
    [
        'id' => 5,
        'name' => 'Книга "PHP для начинающих"',
        'price' => 899.00,
        'tags' => ['книги', 'программирование', 'обучение']
    ],
    [
        'id' => 6,
        'name' => 'Кроссовки RunFast',
        'price' => 4599.99,
        'tags' => ['одежда', 'обувь', 'спорт']
    ],
    [
        'id' => 7,
        'name' => 'Футболка Classic',
        'price' => 1299.00,
        'tags' => ['одежда', 'футболка', 'базовое']
    ],
    [
        'id' => 8,
        'name' => 'Кофеварка Espresso',
        'price' => 8990.00,
        'tags' => ['кухня', 'техника', 'кофе']
    ],
    [
        'id' => 9,
        'name' => 'Микроволновка MWO',
        'price' => 6499.90,
        'tags' => ['кухня', 'техника', 'разогрев']
    ],
    [
        'id' => 10,
        'name' => 'Игровая мышь Gamer',
        'price' => 2990.00,
        'tags' => ['электроника', 'компьютеры', 'игры']
    ],
    [
        'id' => 11,
        'name' => 'Коврик для мыши',
        'price' => 490.00,
        'tags' => ['аксессуары', 'компьютеры']
    ],
    [
        'id' => 12,
        'name' => 'Рюкзак CityPack',
        'price' => 3490.00,
        'tags' => ['аксессуары', 'сумки', 'город']
    ],
    [
        'id' => 13,
        'name' => 'Зонт автоматический',
        'price' => 1190.00,
        'tags' => ['аксессуары', 'дождь']
    ],
    [
        'id' => 14,
        'name' => 'Кружка термо',
        'price' => 890.00,
        'tags' => ['посуда', 'термос']
    ],
    [
        'id' => 15,
        'name' => 'Пауэрбанк 10000mAh',
        'price' => 1990.00,
        'tags' => ['электроника', 'зарядка', 'гаджеты']
    ]
];


$q = $_GET['q'] ?? 'зарядка';
$min = $_GET['min'] ?? 0;
$max = $_GET['max'] ?? PHP_INT_MAX;
$sort = $_GET['sort'] ?? 'price';
$dir = $_GET['dir'] ?? 'asc';
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['perPage']) ? (int)$_GET['perPage'] : 1;


$filtered_arr = array_filter($products, function($subarray) use ($q, $min, $max) 
{
    if(!($subarray['price'] >= $min and $subarray['price'] <= $max))
    {
        return false;
    }
    if(stripos($subarray['name'], $q) !== false)
    {
        return true;
    }
    foreach($subarray['tags'] as $tag)
    {
        if(stripos($tag, $q) !== false)
        {
            return true;
        }
    }
    return false;
});

usort($filtered_arr, function($a, $b) use ($sort, $dir) {
    if ($sort === 'price' && $dir === 'desc') {
        return $b['price'] <=> $a['price'];
    } elseif ($sort === 'name' && $dir === 'asc') {
        return strcmp($a['name'], $b['name']);
    } elseif ($sort === 'name' && $dir === 'desc') {
        return strcmp($b['name'], $a['name']);
    } else {
        return $a['price'] <=> $b['price'];
    }
});

// Пагинация
if ($current_page < 1) $current_page = 1;
$total_items = count($filtered_arr);
$total_pages = ceil($total_items / $limit);

if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
}

$offset = ($current_page - 1) * $limit;
$page_items = array_slice($filtered_arr, $offset, $limit);

echo '<pre>';
print_r($page_items);
echo '</pre>';
?> 