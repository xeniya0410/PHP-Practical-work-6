<?php

// Функция для вывода ошибки и остановки
function die_with_error($message)
{
    echo "<h2>Ошибка загрузки:</h2>";
    echo "<p>{$message}</p>";
    echo "<p><a href='index.html'>Вернуться к загрузке</a></p>";
    exit();
}

// 1. Проверить, что файл действительно передан [cite: 205]
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] === UPLOAD_ERR_NO_FILE) {
    die_with_error("Файл не был выбран для загрузки. Пожалуйста, выберите файл.");
}

$file = $_FILES['csv_file'];

// Проверка на ошибки загрузки PHP
if ($file['error'] !== UPLOAD_ERR_OK) {
    die_with_error("Произошла ошибка при загрузке файла. Код ошибки: {$file['error']}");
}

// 2. Разрешается загружать только файлы с расширением .csv [cite: 203]
$fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
if (strtolower($fileExtension) !== 'csv') {
    die_with_error("Неверное расширение файла. Разрешены только файлы с расширением **.csv**.");
}

// 3. Проверить размер файла (например, не более 2 МБ) [cite: 207]
$maxSize = 2 * 1024 * 1024; // 2 МБ в байтах
if ($file['size'] > $maxSize) {
    die_with_error("Файл слишком большой. Максимальный размер файла - 2 МБ.");
}

// 4. Проверить допустимость имени (без спецсимволов и пробелов в начале) [cite: 206]
// Простое очищение имени файла для безопасности
$originalName = basename($file['name']);
// Удаляем спецсимволы, оставляем только буквы, цифры, точки и дефисы
$cleanName = preg_replace('/[^a-zA-Z0-9\._-]/', '_', $originalName);

// 5. Проверить структуру CSV: не менее 3 столбцов в первой строке [cite: 208]
$requiredColumns = 3;
if (($handle = fopen($file['tmp_name'], "r")) !== FALSE) {
    $data = fgetcsv($handle, 1000, ","); // Считываем первую строку
    fclose($handle);

    if (count($data) < $requiredColumns) {
        die_with_error("Неверная структура CSV. Ожидается минимум **{$requiredColumns}** столбца, найдено **" . count($data) . "**.");
    }
} else {
    die_with_error("Не удалось открыть временный файл для проверки структуры.");
}

// 6. Сохранить файл в папку 'uploads' с уникальным именем (с меткой времени) [cite: 209]
$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Уникальное имя файла: метка времени + исходное имя
$uniqueFileName = time() . '_' . $cleanName;
$targetPath = $uploadDir . $uniqueFileName;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // 7. После успешной загрузки - перенаправление на stats.php [cite: 210]
    // Передача имени загруженного файла через GET-параметр [cite: 211]
    header("Location: stats.php?file=" . urlencode($targetPath));
    exit();
} else {
    // Если произойдёт ошибка, то должно быть выведено сообщение пользователю [cite: 212]
    die_with_error("Ошибка при сохранении файла на сервере. Возможно, проблемы с правами доступа.");
}

?>