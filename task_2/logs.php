<?php

// 1. Исходный файл
$sourceFile = 'C:\Users\Lenovo\Desktop\src\task_2\logs\access.log';

// 2. Выбор метода (Дополнительное требование: ввод с клавиатуры)
$method = "GET";
// Для реализации через ввод с клавиатуры в консольном скрипте можно использовать:
// $method = readline("Введите HTTP-метод (GET или POST): ");
// $method = strtoupper(trim($method));

// Использование switch-case для выбора метода (дополнительное требование)
switch ($method) {
    case 'GET':
    case 'POST':
        break; 
    default:
        echo "Ошибка: Недопустимый метод. Используйте GET или POST." . PHP_EOL;
        exit(1);
}

// 3. Функция filterLogs($filename, $method)
// Фильтрует лог-файл по HTTP-методу и записывает результат в новый файл.

function filterLogs($filename, $method)
{
    // Определяем имя выходного файла
    $targetFile = "logs_" . strtoupper($method) . ".logs";

    // 1. Проверяем существование файла
    if (!file_exists($filename)) {
        echo "Ошибка: Исходный файл **{$filename}** не найден." . PHP_EOL;
        return false;
    }

    // Считываем содержимое файла построчно (используем file() для простоты, т.к. оно возвращает массив строк)
    $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); // Считываем файл в массив 
    if ($lines === false) {
        echo "Ошибка: Не удалось считать содержимое файла **{$filename}**." . PHP_EOL;
        return false;
    }

    // Открываем файл для записи с помощью указателя fp_out. Режим 'w' (write) или 'a' (append), 'w' предпочтительнее для перезаписи 
    $fp_out = fopen($targetFile, "w");
    if (!$fp_out) {
        echo "Ошибка: Не удалось открыть/создать файл **{$targetFile}** для записи." . PHP_EOL;
        return false;
    }

    $count = 0;
    $searchPattern1 = " " . $method . " "; // Пробел + Метод + Пробел
    $searchPattern2 = "\"" . $method . " "; // Кавычка + Метод + Пробел

    foreach ($lines as $line) {
        if (str_contains($line, $searchPattern1) || str_contains($line, $searchPattern2)) {
            fwrite($fp_out, $line . PHP_EOL);
            $count++;
        }
    }
// Создаёт счётчик найденных строк. Готовит два шаблона поиска для GET/POST (один с пробелами, другой с кавычкой). 
// Проходит по каждой строке лога. Если строка содержит метод, записывает её в новый файл. Увеличивает счётчик. В конце функция возвращает $count.

    // 4. Закрывает оба файла. (Исходный файл закрыт функцией file() автоматически)
    fclose($fp_out);

    return $count;
}

// Вызов функции и вывод результата

echo "--- Фильтрация логов по методу **{$method}** ---" . PHP_EOL; //php_eol - константа для верного отображения строк

$lineCount = filterLogs($sourceFile, $method);

if ($lineCount !== false) {
    $targetFile = "logs_" . strtoupper($method) . ".logs";
    // 5. После вызова функции, выведите сообщение с подсчётом строк
    echo "Файл [{$targetFile}] был создан. В нём содержится **{$lineCount}** строк(и)." . PHP_EOL;
}


?>

