# Отчёт о миграции на PHP 8.5
Проверка phpcs
![img.png](img.png)

Rector
``` ./vendor/bin/rector process --dry-run > rector-dry-run.txt           ```
![img_1.png](img_1.png)

```./vendor/bin/phpcs src/  public/ --standard=PHPCompatibility --runtime-set testVersion 8.5- -s > phpcs-before.txt```
![img_2.png](img_2.png)

```./vendor/bin/rector process```
![img_3.png](img_3.png)

![img_4.png](img_4.png)