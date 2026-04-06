<?php

class ArrayWrapper
{
    protected array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function __get(string $name)
    {
        return $this->data[$name] ?? null;
    }

    public function __set(string $name, $value): void
    {
        $this->data[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    public function __unset(string $name): void
    {
        unset($this->data[$name]);
    }

    public function __toString(): string
    {
        return json_encode($this->data, JSON_UNESCAPED_UNICODE);
    }

    public function __invoke(?string $key = null)
    {
        if ($key === null) {
            return $this->data;
        }
        return $this->data[$key] ?? null;
    }

    public function __clone()
    {
        $this->data = $this->deepClone($this->data);
    }


    private function deepClone($value)
    {
        if (is_object($value)) {
            return clone $value;
        }
        if (is_array($value)) {
            $newArray = [];
            foreach ($value as $key => $item) {
                $newArray[$key] = $this->deepClone($item);
            }
            return $newArray;
        }
        return $value;
    }
}
