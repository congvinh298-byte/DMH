<?php
$pdo = new PDO('mysql:host=localhost;dbname=kwkrbcce_Goixelapvo;charset=utf8mb4', 'root', '');
$stmt = $pdo->query('DESCRIBE orders');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
