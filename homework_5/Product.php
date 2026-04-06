<?php

class Product {
    private $id;
    private $title;
    private $price;
    
    public function __construct($id, $title, $price) {
        $this->id = $id;
        $this->title = $title;
        $this->price = $price;
    }
    
    public function getId() {
        return $this->id;
    }
    
    public function getTitle() {
        return $this->title;
    }
    
    public function getPrice() {
        return $this->price;
    }
}