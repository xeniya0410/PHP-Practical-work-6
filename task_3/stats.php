<?php

// Устанавливаем часовой пояс для корректного отображения даты
date_default_timezone_set('Asia/Almaty');

// Функция для вывода ошибки и остановки
function die_with_error($message)
{
    echo "<h1>Ошибка</h1>";
    echo "<p>{$message}</p>";
    echo "<p><a href='index.html'>Вернуться к загрузке</a></p>";
    exit();
}

// Вспомогательная функция для отображения размера файла в удобном формате (МБ, КБ) 
function format_bytes($bytes)
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' МБ';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' КБ';
    } elseif ($bytes > 1) {
        return $bytes . ' байт';
    } elseif ($bytes == 1) {
        return $bytes . ' байт';
    } else {
        return '0 байт';
    }
}

// 1. Проверка GET-параметра и загрузка данных

if (!isset($_GET['file'])) {
    die_with_error("Не указан файл для анализа. Пожалуйста, загрузите его через форму.");
}

$uploadedFilePath = $_GET['file']; // Получаем путь к загруженному файлу 

if (!file_exists($uploadedFilePath)) {
    die_with_error("Файл **{$uploadedFilePath}** не найден на сервере.");
}

// Считывание CSV-файла с использованием fgetcsv() 
// fgetcsv() - Функция fgetcsv читает строку из файла и разбирает ее на поля в формате CSV. Первым параметром функция принимает указатель на открытый файл, вторым - максимальную длину строки, третьим - разделитель полей (по умолчанию запятая), четвертым - символ ограничителя (по умолчанию двойные кавычки), пятым - символ экранирования.
// Чтобы использовать специальный символ как обычный, добавьте к нему обратную косую черту: \. Это называется «экранирование символа»
// Формат CSV - Comma-Separated Values — значения, разделённые запятыми — текстовый формат для хранения табличных данных
$data = [];
if (($handle = fopen($uploadedFilePath, "r")) !== FALSE) {
    $header = fgetcsv($handle, 1000, ",", '"', '\\');
    if (!$header) {
        die_with_error("Файл пуст или имеет неверный формат заголовка.");
    }
    while (($row = fgetcsv($handle, 1000, ",", '"', '\\')) !== FALSE) {
        // Убедимся, что строка имеет то же количество столбцов, что и заголовок
        if (count($row) === count($header)) {
            $data[] = array_combine($header, $row); //преобразуем строки в ассоциативные массивы array_combine($header, $row) и сохраняем в $data.
        } else {
            // Игнорируем строки с неверным количеством столбцов
        }
    }
    fclose($handle);
} else {
    die_with_error("Не удалось открыть файл для чтения.");
}

$numRows = count($data);
$numCols = count($header);
//Считаем количество строк и столбцов в CSV

function analyze_csv($data, $header)
{
    $numRows = count($data);
    $stats = [ //Создаем массив $stats для хранения результатов анализа
        'empty_cells_count' => 0,
        'runtime_sum' => 0,
        'genre_counts' => [],
        'director_counts' => [],
    ];

    foreach ($data as $row) {
        $hasEmptyCell = false;

        // a) Подсчёт фильмов с пустыми ячейками 
        foreach ($row as $key => $value) {
            if (empty(trim($value))) {
                $hasEmptyCell = true;
                break;
            }
        }
        if ($hasEmptyCell) {
            $stats['empty_cells_count']++; //Проходим по каждой строке. Считаем строки, в которых есть пустые ячейки
        }

        // b) Подсчёт продолжительности фильма (для средней) Суммируем продолжительность фильмов 
        if (isset($row['runtime'])) {
            // Извлекаем только число, убирая " min"
            if (preg_match('/(\d+)/', $row['runtime'], $matches)) {
                $stats['runtime_sum'] += (int) $matches[1];
            }
        }

        // c) Разбираем жанры (если несколько через запятую), считаем количество каждого жанра
        if (isset($row['genre']) && !empty($row['genre'])) {
            // Обработка множественных жанров (например, "Crime, Drama")
            $genres = array_map('trim', explode(',', str_replace(['"', "'"], '', $row['genre'])));
            foreach ($genres as $genre) {
                if (!empty($genre)) {
                    $stats['genre_counts'][$genre] = ($stats['genre_counts'][$genre] ?? 0) + 1;
                }
            }
        }

        // d) Подсчёт популярности режиссёров. Считаем количество фильмов каждого режиссёра.
        if (isset($row['director']) && !empty($row['director'])) {
            $director = trim($row['director']);
            $stats['director_counts'][$director] = ($stats['director_counts'][$director] ?? 0) + 1;
        }
    }

    // Финальный подсчет статистики
    $stats['average_runtime'] = $numRows > 0 ? round($stats['runtime_sum'] / $numRows) : 0;

    // Два самых часто встречающихся жанра
    arsort($stats['genre_counts']);
    $stats['top_genres'] = array_slice($stats['genre_counts'], 0, 2, true);

    // Самый популярный режиссёр
    arsort($stats['director_counts']);
    $stats['top_director'] = key($stats['director_counts']) ?? 'N/A';

    return $stats;
}

$analysis = analyze_csv($data, $header);

// 3. Функция для реализации фильтрации

function filter_data($data, $filterType)
{
    $filteredData = [];

    foreach ($data as $row) {
        $matches = false;

        // Извлечение числовых данных для сравнения
        preg_match('/(\d+)/', $row['runtime'] ?? '0', $runtimeMatch);
        $runtime = (int) ($runtimeMatch[1] ?? 0);

        preg_match('/\((\d{4})\)/', $row['release_year'] ?? '(0)', $yearMatch);
        $year = (int) ($yearMatch[1] ?? 0);

        $rating = (float) ($row['rating'] ?? 0);

        // Извлечение числа дохода (например, $28.34M -> 28.34)
        $grossValue = 0.0;
        if (isset($row['gross']) && preg_match('/\$(\d+(\.\d+)?)M/', $row['gross'], $grossMatch)) {
            $grossValue = (float) ($grossMatch[1] ?? 0.0);
        }

        switch ($filterType) {
            case 'long_low_rating':
                // 10 самых длительных фильмов с рейтингом ниже 8.0
                if ($rating < 8.0) {
                    $matches = true;
                }
                break;
            case 'scifi_after_2015':
                // Фильмы, вышедшие после 2015 года в жанре Sci-Fi
                if ($year > 2015 && str_contains(strtolower($row['genre'] ?? ''), 'sci-fi')) {
                    $matches = true;
                }
                break;
            case 'old_low_gross':
                // Фильмы, вышедшие до или в 1980 году, у которых общий доход меньше 10.00M 
                if ($year <= 1980 && $grossValue < 10.00) {
                    $matches = true;
                }
                break;
        }

        if ($matches) {
            $filteredData[] = $row;
        }
    }

    // Дополнительная сортировка для "long_low_rating"
    if ($filterType === 'long_low_rating') {
        // Сортируем по продолжительности (runtime) в порядке убывания
        usort($filteredData, function ($a, $b) {
            preg_match('/(\d+)/', $a['runtime'] ?? '0', $aMatch);
            preg_match('/(\d+)/', $b['runtime'] ?? '0', $bMatch);
            return (int) ($bMatch[1] ?? 0) <=> (int) ($aMatch[1] ?? 0);
        });
        // Выбираем только 10 самых длительных
        $filteredData = array_slice($filteredData, 0, 10);
    }

    return $filteredData;
}

$filterResult = [];
$selectedFilter = $_GET['filter'] ?? null;
if ($selectedFilter) {
    $filterResult = filter_data($data, $selectedFilter);
}

// Получаем информацию о файле: размер, время последней модификации, исходное имя

// Получаем информацию о файле
$fileInfo = stat($uploadedFilePath);
$originalFileName = pathinfo($uploadedFilePath, PATHINFO_BASENAME);
$fileSize = $fileInfo['size'];
$uploadTime = date('Y-m-d H:i:s', $fileInfo['mtime']);

// Заглушка для исходного имени файла (поскольку в upload.php оно не передается, используем его для отображения)
$parts = explode('_', $originalFileName, 2);
$initialName = count($parts) > 1 ? $parts[1] : $originalFileName;


?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Статистика и анализ CSV</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 20px;
        }

        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .stats-table th,
        .stats-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .stats-table th {
            background-color: #f2f2f2;
        }
    </style>
</head>

<body>
    <h1>Анализ файла с фильмами</h1>

    <h2>Общие сведения о файле</h2>
    <table class="stats-table">
        <tr>
            <th>Исходное имя файла </th>
            <td><?php echo htmlspecialchars($initialName); ?></td>
        </tr>
        <tr>
            <th>Новый путь и имя сохранённого файла </th>
            <td><?php echo htmlspecialchars($uploadedFilePath); ?></td>
        </tr>
        <tr>
            <th>Размер файла (байты) </th>
            <td><?php echo htmlspecialchars($fileSize); ?></td>
        </tr>
        <tr>
            <th>Размер файла (удобный формат) </th>
            <td><?php echo htmlspecialchars(format_bytes($fileSize)); ?></td>
        </tr>
        <tr>
            <th>Дата и время загрузки </th>
            <td><?php echo htmlspecialchars($uploadTime); ?></td>
        </tr>
        <tr>
            <th>Количество строк (без заголовка) </th>
            <td><?php echo $numRows; ?></td>
        </tr>
        <tr>
            <th>Количество столбцов </th>
            <td><?php echo $numCols; ?></td>
        </tr>
    </table>

    <h2>Результаты анализа</h2>
    <table class="stats-table">
        <tr>
            <th>Количество фильмов с пустыми ячейками </th>
            <td><?php echo $analysis['empty_cells_count']; ?></td>
        </tr>
        <tr>
            <th>Два самых часто встречающихся жанра </th>
            <td>
                <?php foreach ($analysis['top_genres'] as $genre => $count): ?>
                    <?php echo htmlspecialchars($genre) . " ({$count} фильмов)<br>"; ?>
                <?php endforeach; ?>
            </td>
        </tr>
        <tr>
            <th>Средняя продолжительность фильма (мин) </th>
            <td><?php echo $analysis['average_runtime']; ?> мин</td>
        </tr>
        <tr>
            <th>Самый популярный режиссёр </th>
            <td><?php echo htmlspecialchars($analysis['top_director']); ?>
                (<?php echo $analysis['director_counts'][$analysis['top_director']] ?? 0; ?> фильмов)</td>
        </tr>
    </table>

    <h2>Фильтрация данных</h2>

    <form method="GET" action="stats.php">
        <input type="hidden" name="file" value="<?php echo htmlspecialchars($uploadedFilePath); ?>">
        <label for="filter">Выберите тип фильтрации:</label>
        <select name="filter" id="filter" onchange="this.form.submit()"> <!--При выборе фильтра форма автоматически отправляется (onchange="this.form.submit()"-->
            <option value="">-- Выберите фильтр --</option>
            <option value="long_low_rating" <?php echo $selectedFilter === 'long_low_rating' ? 'selected' : ''; ?>>
                10 самых длительных фильмов с рейтингом < 8.0 </option>
            <option value="scifi_after_2015" <?php echo $selectedFilter === 'scifi_after_2015' ? 'selected' : ''; ?>>
                Фильмы, вышедшие после 2015 года в жанре Sci-Fi
            </option>
            <option value="old_low_gross" <?php echo $selectedFilter === 'old_low_gross' ? 'selected' : ''; ?>>
                Фильмы до 1980 года с доходом < 10.00M </option>
        </select>
    </form>
    <br>

    <?php if ($selectedFilter && $filterResult): ?>
        <h3>Результаты фильтрации (Найдено: <?php echo count($filterResult); ?>)</h3>
        <table class="stats-table">
            <thead>
                <tr>
                    <th>title</th>
                    <th>director</th>
                    <th>release_year</th>
                    <th>runtime</th>
                    <th>rating</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filterResult as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['title'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['director'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['release_year'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['runtime'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['rating'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif ($selectedFilter && !$filterResult): ?>
        <p>По выбранному фильтру данные не найдены.</p>
    <?php endif; ?>

</body>


</html>


