<?php

namespace MyApp\routing;

class ClassesGetter
{
    public static function getClasses(): array
    {
        $path = __DIR__ . "\..";
        $foundClasses = [];

        $allFiles = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        $phpFiles = new \RegexIterator($allFiles, '/\.php$/');

        foreach ($phpFiles as $file) {
            if ($file->getFilename() === 'classesGetter.php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            $namespace = '';
            if (preg_match('/namespace\s+(.+?);/', $content, $m)) {
                $namespace = trim($m[1]) . '\\';
            }

            if (preg_match('/class\s+(\w+)/', $content, $m)) {
                $className = $m[1];
                $foundClasses[] = $namespace . $className;
            }
        }

        return $foundClasses;
    }
}
