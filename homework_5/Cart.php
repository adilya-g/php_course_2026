<?php
require_once 'Product.php';

class Cart {
    private $items = []; 
    

    public function add(Product $product, int $quantity = 1): void {
        $productId = $product->getId();
        
        if (isset($this->items[$productId])) {
            $this->items[$productId]['quantity'] += $quantity;
        } else {
            $this->items[$productId] = [
                'product' => $product,
                'quantity' => $quantity
            ];
        }
    }
    

    public function remove(int $productId): bool {
        if (isset($this->items[$productId])) {
            unset($this->items[$productId]);
            return true;
        }
        return false;
    }
    

    public function getItems(): array {
        return $this->items;
    }
    

    public function getTotal(): float {
        $total = 0;
        
        foreach ($this->items as $item) {
            $price = $item['product']->getPrice();
            $quantity = $item['quantity'];
            $total += $price * $quantity;
        }
        
        return $total;
    }
    

    public function clear(): void {
        $this->items = [];
    }
    

    public function getUniqueItemsCount(): int {
        return count($this->items);
    }
    

    public function getTotalQuantity(): int {
        $total = 0;
        
        foreach ($this->items as $item) {
            $total += $item['quantity'];
        }
        
        return $total;
    }
    

    public function updateQuantity(int $productId, int $quantity): bool {
        if (isset($this->items[$productId])) {
            if ($quantity <= 0) {
                return $this->remove($productId);
            }
            $this->items[$productId]['quantity'] = $quantity;
            return true;
        }
        return false;
    }
}