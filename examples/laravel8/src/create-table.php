<?php
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

$pdo->exec("CREATE TABLE fruits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL
)");

$sql = "INSERT INTO fruits (name) VALUES (?)";
$stmt = $pdo->prepare($sql);

$fruits = ['Apple', 'Orange', 'Grape', 'Banana', 'Strawberry'];
foreach ($fruits as $fruit) {
    $stmt->execute([$fruit]);
}
