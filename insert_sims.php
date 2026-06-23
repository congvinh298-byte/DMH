<?php
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim(str_replace("\xEF\xBB\xBF", '', $line));
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }

        [$name, $value] = array_map('trim', explode('=', $line, 2));
        if ($name !== '') {
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') || (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            putenv($name . '=' . $value);
        }
    }
}

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_NAME') ?: '';
$user = getenv('DB_USER') ?: '';
$passValue = getenv('DB_PASS');
$pass = $passValue === false ? '' : $passValue;
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';

if ($db === '' || $user === '') {
    fwrite(STDERR, "Missing database credentials. Set DB_NAME and DB_USER in .env.\n");
    exit(1);
}

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);

$simsData = <<<JSON
[
    {"so_sim": "0795 841 841", "gia_ban": 6900000},
    {"so_sim": "0764 123 567", "gia_ban": 9900000},
    {"so_sim": "0784 123 567", "gia_ban": 9900000},
    {"so_sim": "0907 542 789", "gia_ban": 3900000},
    {"so_sim": "0788 871 386", "gia_ban": 2600000},
    {"so_sim": "0768 471 368", "gia_ban": 2900000},
    {"so_sim": "0931 042 567", "gia_ban": 2000000},
    {"so_sim": "0787 827 939", "gia_ban": 2500000},
    {"so_sim": "0766 967 939", "gia_ban": 2900000},
    {"so_sim": "0798 770 222", "gia_ban": 1200000},
    {"so_sim": "0702 977 333", "gia_ban": 1300000},
    {"so_sim": "0795 941 555", "gia_ban": 1500000},
    {"so_sim": "0762 823 555", "gia_ban": 1500000},
    {"so_sim": "0939 601 789", "gia_ban": 9900000},
    {"so_sim": "0939 70 42 42", "gia_ban": 2900000},
    {"so_sim": "0939 736 567", "gia_ban": 2900000},
    {"so_sim": "0932 820 567", "gia_ban": 2900000},
    {"so_sim": "0795 496 555", "gia_ban": 1500000},
    {"so_sim": "0931 070 456", "gia_ban": 2900000},
    {"so_sim": "0768 889 994", "gia_ban": 1600000},
    {"so_sim": "070 242 9979", "gia_ban": 1000000},
    {"so_sim": "0767 939 959", "gia_ban": 9900000},
    {"so_sim": "0787 815 789", "gia_ban": 2900000},
    {"so_sim": "0776 80 80 08", "gia_ban": 6800000},
    {"so_sim": "0763 225 777", "gia_ban": 2900000},
    {"so_sim": "0782 884 123", "gia_ban": 1000000},
    {"so_sim": "0704 866 234", "gia_ban": 1000000},
    {"so_sim": "0931 007 456", "gia_ban": 2900000},
    {"so_sim": "0899 014 678", "gia_ban": 1000000},
    {"so_sim": "0901 08 38 79", "gia_ban": 1600000},
    {"so_sim": "0788 756 088", "gia_ban": 1000000},
    {"so_sim": "0901 757 044", "gia_ban": 1000000},
    {"so_sim": "0939 513 996", "gia_ban": 1000000},
    {"so_sim": "0939 516 009", "gia_ban": 1000000},
    {"so_sim": "0939 515 977", "gia_ban": 1000000},
    {"so_sim": "0777 832 899", "gia_ban": 1000000},
    {"so_sim": "0786 917 089", "gia_ban": 1000000},
    {"so_sim": "0766 958 279", "gia_ban": 1000000},
    {"so_sim": "0769 317 137", "gia_ban": 1000000},
    {"so_sim": "0776 51 79 88", "gia_ban": 1000000},
    {"so_sim": "0788 71 39 79", "gia_ban": 3000000},
    {"so_sim": "0931 00 78 38", "gia_ban": 2000000},
    {"so_sim": "0776 80 39 79", "gia_ban": 3900000},
    {"so_sim": "0794 933336", "gia_ban": 3900000},
    {"so_sim": "0789 678 168", "gia_ban": 6000000},
    {"so_sim": "0907 288 234", "gia_ban": 3000000},
    {"so_sim": "0939 944 886", "gia_ban": 3000000},
    {"so_sim": "0907 607 456", "gia_ban": 3900000},
    {"so_sim": "0932 999 811", "gia_ban": 2900000},
    {"so_sim": "0932 833 949", "gia_ban": 2000000},
    {"so_sim": "0789 68 18 79", "gia_ban": 3000000},
    {"so_sim": "0704 934 555", "gia_ban": 1500000},
    {"so_sim": "0939 91 35 85", "gia_ban": 1000000},
    {"so_sim": "0939 464 776", "gia_ban": 1000000},
    {"so_sim": "0788 925 199", "gia_ban": 1000000},
    {"so_sim": "0799 52 68 38", "gia_ban": 1000000},
    {"so_sim": "0939 168 114", "gia_ban": 2000000},
    {"so_sim": "0779 85 38 78", "gia_ban": 2000000},
    {"so_sim": "0904 10 73 78", "gia_ban": 1000000},
    {"so_sim": "0939 519 869", "gia_ban": 1000000},
    {"so_sim": "0939 42 03 04", "gia_ban": 1000000},
    {"so_sim": "0939 424 357", "gia_ban": 1000000},
    {"so_sim": "0766 949 115", "gia_ban": 1000000},
    {"so_sim": "0769 364 068", "gia_ban": 1000000},
    {"so_sim": "0939 234 993", "gia_ban": 1000000},
    {"so_sim": "0767 50 70 90", "gia_ban": 6800000},
    {"so_sim": "0762 678 123", "gia_ban": 6000000},
    {"so_sim": "0931 07 57 39", "gia_ban": 1000000},
    {"so_sim": "0904 220 538", "gia_ban": 1000000},
    {"so_sim": "0934 899 466", "gia_ban": 1000000},
    {"so_sim": "0932 884 772", "gia_ban": 1000000},
    {"so_sim": "0767 971 386", "gia_ban": 2000000},
    {"so_sim": "0939 08 67 87", "gia_ban": 1000000},
    {"so_sim": "0934 784 739", "gia_ban": 1000000},
    {"so_sim": "0904 006 484", "gia_ban": 1000000},
    {"so_sim": "0937 11 44 09", "gia_ban": 1000000},
    {"so_sim": "0934 46 29 52", "gia_ban": 1000000},
    {"so_sim": "0904 007 115", "gia_ban": 2000000},
    {"so_sim": "0939 51 18 79", "gia_ban": 1000000},
    {"so_sim": "0788 968 739", "gia_ban": 1000000},
    {"so_sim": "0907 37 55 52", "gia_ban": 1000000},
    {"so_sim": "0786 81 78 38", "gia_ban": 2000000},
    {"so_sim": "0786 88 43 21", "gia_ban": 1000000},
    {"so_sim": "0788 72 03 69", "gia_ban": 1000000},
    {"so_sim": "078 78 78 457", "gia_ban": 1000000},
    {"so_sim": "0787 918 939", "gia_ban": 2000000},
    {"so_sim": "0788 75 83 86", "gia_ban": 3000000},
    {"so_sim": "07 888 000 64", "gia_ban": 1000000},
    {"so_sim": "0905 02 42 99", "gia_ban": 1000000},
    {"so_sim": "0902 033 494", "gia_ban": 1000000},
    {"so_sim": "0901 977 239", "gia_ban": 1000000},
    {"so_sim": "0934 578 452", "gia_ban": 1000000},
    {"so_sim": "0769 846 929", "gia_ban": 1000000},
    {"so_sim": "0797 546 909", "gia_ban": 1000000},
    {"so_sim": "079 74 37 949", "gia_ban": 1000000},
    {"so_sim": "079 68 37 949", "gia_ban": 1000000},
    {"so_sim": "0794 978 987", "gia_ban": 1000000},
    {"so_sim": "07971 07972", "gia_ban": 1000000},
    {"so_sim": "0907 601 855", "gia_ban": 1000000},
    {"so_sim": "0907 4 4 2026", "gia_ban": 2000000},
    {"so_sim": "0907 893 442", "gia_ban": 1000000},
    {"so_sim": "0939 385 366", "gia_ban": 1000000},
    {"so_sim": "0932 952 117", "gia_ban": 1000000},
    {"so_sim": "0907 980 739", "gia_ban": 1000000},
    {"so_sim": "0939 455 086", "gia_ban": 1000000},
    {"so_sim": "0907 160 252", "gia_ban": 1000000},
    {"so_sim": "0907 64 79 52", "gia_ban": 1000000},
    {"so_sim": "0936 646 577", "gia_ban": 1000000},
    {"so_sim": "0935 38 24 38", "gia_ban": 3000000},
    {"so_sim": "0932 445 411", "gia_ban": 1000000},
    {"so_sim": "093 4449 611", "gia_ban": 1000000},
    {"so_sim": "090 441 39 29", "gia_ban": 1000000},
    {"so_sim": "0932 883 002", "gia_ban": 1000000},
    {"so_sim": "093 28 29 112", "gia_ban": 1000000},
    {"so_sim": "077 989 0008", "gia_ban": 1000000},
    {"so_sim": "0939 770 389", "gia_ban": 1500000},
    {"so_sim": "0907 025 768", "gia_ban": 1000000},
    {"so_sim": "0907 62 1238", "gia_ban": 1000000},
    {"so_sim": "0939 87 31 38", "gia_ban": 1000000},
    {"so_sim": "0932 918 278", "gia_ban": 1000000},
    {"so_sim": "0799 61 1568", "gia_ban": 1000000},
    {"so_sim": "0939 101 344", "gia_ban": 1000000},
    {"so_sim": "0939 615 078", "gia_ban": 1000000},
    {"so_sim": "0907 33 24 38", "gia_ban": 1000000},
    {"so_sim": "0939 265 479", "gia_ban": 1000000},
    {"so_sim": "0939 79 42 19", "gia_ban": 1000000},
    {"so_sim": "0939 54 09 09", "gia_ban": 10000000},
    {"so_sim": "0939 29 79 10", "gia_ban": 1000000},
    {"so_sim": "0932 810 552", "gia_ban": 1000000},
    {"so_sim": "0907 31 62 67", "gia_ban": 1000000},
    {"so_sim": "0907 258 122", "gia_ban": 1000000},
    {"so_sim": "0787 911 539", "gia_ban": 1000000},
    {"so_sim": "0901 704 589", "gia_ban": 1000000},
    {"so_sim": "090 19 19 114", "gia_ban": 2000000},
    {"so_sim": "0934 393 775", "gia_ban": 1000000},
    {"so_sim": "0939 89 09 43", "gia_ban": 1000000},
    {"so_sim": "0797 12 78 38", "gia_ban": 1000000},
    {"so_sim": "0794 80 78 38", "gia_ban": 1000000},
    {"so_sim": "0907 24 52 38", "gia_ban": 2000000},
    {"so_sim": "0907 52 18 79", "gia_ban": 3000000}
]
JSON;

$sims = json_decode($simsData, true);

function detect_network($number) {
    $number = preg_replace('/[^0-9]/', '', $number);
    $prefix3 = substr($number, 0, 3);
    $prefix4 = substr($number, 0, 4);

    if (in_array($prefix3, ['086', '096', '097', '098', '032', '033', '034', '035', '036', '037', '038', '039'])) return 'Viettel';
    if (in_array($prefix3, ['089', '090', '093', '070', '079', '077', '076', '078'])) return 'MobiFone';
    if (in_array($prefix3, ['088', '091', '094', '083', '084', '085', '081', '082'])) return 'VinaPhone';
    if (in_array($prefix3, ['092', '056', '058'])) return 'Vietnamobile';
    if (in_array($prefix3, ['099', '059'])) return 'Gmobile';
    if (in_array($prefix3, ['087'])) return 'ITelecom';
    if (in_array($prefix3, ['055'])) return 'Reddi';
    
    return 'Khác';
}

function sim_digits($number) {
    return preg_replace('/[^0-9]/', '', (string)$number);
}

function qi($identifier) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', (string)$identifier)) {
        throw new RuntimeException('Unsafe database identifier.');
    }
    return "`$identifier`";
}

$stmt = $pdo->query("SHOW TABLES LIKE 'sims'");
$hasSims = $stmt->rowCount() > 0;
$stmt = $pdo->query("SHOW TABLES LIKE 'marketplace_sims'");
$hasMarketplaceSims = $stmt->rowCount() > 0;

$targetTable = $hasMarketplaceSims ? 'marketplace_sims' : ($hasSims ? 'sims' : null);

if (!$targetTable) {
    echo "NO TABLE FOUND. CREATING marketplace_sims.\n";
    $pdo->exec("CREATE TABLE marketplace_sims (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone_number VARCHAR(20) NOT NULL,
        price INT NOT NULL,
        network VARCHAR(50) NOT NULL,
        sim_type VARCHAR(50) DEFAULT 'SIM',
        status VARCHAR(30) NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL,
        UNIQUE KEY uniq_marketplace_sims_phone (phone_number),
        INDEX idx_marketplace_sims_price (price),
        INDEX idx_marketplace_sims_status (status)
    )");
    $targetTable = 'marketplace_sims';
}

$stmt = $pdo->query("DESCRIBE " . qi($targetTable));
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_column($cols, 'Field');

$colPhone = in_array('so_sim', $colNames) ? 'so_sim' : (in_array('phone_number', $colNames) ? 'phone_number' : 'name');
$colPrice = in_array('gia_ban', $colNames) ? 'gia_ban' : (in_array('price', $colNames) ? 'price' : 'sale_price');
$colNetwork = in_array('nha_mang', $colNames) ? 'nha_mang' : (in_array('network', $colNames) ? 'network' : null);
$colType = in_array('loai_sim', $colNames) ? 'loai_sim' : (in_array('sim_type', $colNames) ? 'sim_type' : null);
$colStatus = in_array('status', $colNames) ? 'status' : null;
$colCreated = in_array('created_at', $colNames) ? 'created_at' : null;
$colUpdated = in_array('updated_at', $colNames) ? 'updated_at' : null;

$existing = [];
$stmtExisting = $pdo->query("SELECT " . qi($colPhone) . " FROM " . qi($targetTable));
foreach ($stmtExisting->fetchAll(PDO::FETCH_COLUMN) as $phoneRow) {
    $digits = sim_digits($phoneRow);
    if ($digits !== '') {
        $existing[$digits] = true;
    }
}

$count = 0;
foreach ($sims as $sim) {
    $network = detect_network($sim['so_sim']);
    $digits = sim_digits($sim['so_sim']);
    
    if ($digits === '' || isset($existing[$digits])) continue;
    
    $colsToInsert = [$colPhone, $colPrice];
    $vals = [$sim['so_sim'], $sim['gia_ban']];
    $placeholders = ['?', '?'];
    
    if ($colNetwork) {
        $colsToInsert[] = $colNetwork;
        $vals[] = $network;
        $placeholders[] = '?';
    }
    if ($colType) {
        $colsToInsert[] = $colType;
        $vals[] = 'SIM';
        $placeholders[] = '?';
    }
    if ($colStatus) {
        $colsToInsert[] = $colStatus;
        $vals[] = 'active';
        $placeholders[] = '?';
    }
    if ($colCreated) {
        $colsToInsert[] = $colCreated;
        $placeholders[] = 'NOW()';
    }
    if ($colUpdated) {
        $colsToInsert[] = $colUpdated;
        $placeholders[] = 'NOW()';
    }
    
    $sql = "INSERT INTO " . qi($targetTable) . " (" . implode(', ', array_map('qi', $colsToInsert)) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($vals);
    $existing[$digits] = true;
    $count++;
}

echo "Inserted $count SIMs into $targetTable.\n";
