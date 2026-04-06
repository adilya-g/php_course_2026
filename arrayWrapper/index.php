<?php

require_once 'ArrayWrapper.php';

$array = [
    0 => "hi!",
    'str' => "my name is",
    'name' => "Hello World",
];

$wrapped_array = new ArrayWrapper($array);

// Тест __get
echo $wrapped_array->name . "\n"; // Hello World
echo $wrapped_array->str . "\n";  // my name is
echo $wrapped_array->none . "\n"; // null

// Тест __set
$wrapped_array->new_key = "New Value";
echo $wrapped_array->new_key . "\n"; // New Value

// Тест __isset
var_dump(isset($wrapped_array->name)); // true
var_dump(isset($wrapped_array->fake)); // false

// Тест __unset
unset($wrapped_array->str);
var_dump(isset($wrapped_array->str)); // false

// Тест __toString
echo $wrapped_array . "\n"; // {"0":"hi!","name":"Hello World","new_key":"New Value"}

// Тест __invoke
print_r($wrapped_array());        // весь массив
echo $wrapped_array('name') . "\n"; // Hello World
echo $wrapped_array('fake') . "\n"; // null


$cloned = clone $wrapped_array;
$cloned->name = "Changed";
echo $wrapped_array->name . "\n"; // Hello World (оригинал не изменился)
echo $cloned->name . "\n";        // Changed


echo $wrapped_array->get('name') . "\n";     // Hello World
echo $wrapped_array->get('fake', 'default') . "\n"; // default
var_dump($wrapped_array->has('name'));       // true
$wrapped_array->set('age', 25);
echo $wrapped_array->age . "\n";             // 25
print_r($wrapped_array->toArray());