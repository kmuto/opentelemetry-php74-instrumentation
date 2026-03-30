<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    $min_microseconds = 0;
    $max_microseconds = 2000000;
    $random_microseconds = rand($min_microseconds, $max_microseconds);
    usleep($random_microseconds);
    return view('hello', [ 'htmlData' => '' ]);
});

Route::get('/hello', function () {
    $min_microseconds = 0;
    $max_microseconds = 1000000;
    $random_microseconds = rand($min_microseconds, $max_microseconds);
    usleep($random_microseconds);

    $dsn = "mysql:host=db;dbname=laravel;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, "root", null, $options);
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }

    try {
      $stmt = $pdo->prepare("SELECT id, name FROM fruits");
      $stmt->execute();
      $htmlData = '';
      $htmlData .= "<h1>Fruits Table</h1>";
      $htmlData .= "<ul>";
      while ($row = $stmt->fetch()) {
          $htmlData .= "<li>" . htmlspecialchars((string)$row['id']) . ": " . htmlspecialchars($row['name']) . "</li>";
      }
      $htmlData .= "</ul>";
    } catch(\PDOException $e) {
        $htmlData .= "Query error: " . htmlspecialchars($e->getMessage());
    }
    $min_microseconds = 0;
    $max_microseconds = 1000000;
    $random_microseconds = rand($min_microseconds, $max_microseconds);
    usleep($random_microseconds);
    return view('hello', [ 'htmlData' => $htmlData ]);
});

Route::get('/call', function () {
    $response = Http::get('https://jsonplaceholder.typicode.com/posts');
    $htmlData = '<p>Returned from External API Server:</p><div class="pre"><pre>';
    if ($response->successful()) {
        $posts = $response->json();
        $prettyJson = json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $htmlData .= e(mb_substr($prettyJson, 0, 500)) . "...";
    }
    $htmlData .= '</pre></div>';
    if ($response->failed()) {
        $response->throw();
    }
    return view('hello', [ 'htmlData' => $htmlData ]);
});

Route::get('/error', function () {
    1 / 0;
});

Route::get('/query', function () {
  throw new \InvalidArgumentException("Invalid ID: -1");
});
