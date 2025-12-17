<?php
session_start();
include 'db.php';
include 'session.php';
include 'header.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: upload.php");
    exit();
}

$selectedUser   = $_GET['user'] ?? '';
$selectedBank   = $_GET['bank'] ?? '';
$selectedStatus = $_GET['status'] ?? '';
$fromDate       = $_GET['from_date'] ?? '';
$toDate         = $_GET['to_date'] ?? '';

$usersRes = $conn->query("SELECT id, username FROM users ORDER BY username");
$banksRes = $conn->query("SELECT DISTINCT bank_name FROM uploaded_files ORDER BY bank_name");
$statusRes = $conn->query("
    SELECT DISTINCT status 
    FROM uploaded_data 
    WHERE type='unmatched' AND status IS NOT NULL AND status != '' 
    ORDER BY status
");

$sql = "
SELECT 
    ud.id,
    ud.holding_or_tl,
    ud.txn_id,
    ud.date,
    ud.amount,
    ud.gateway,
    ud.payment_type,
    ud.status,
    uf.bank_name,
    u.username
FROM uploaded_data ud
JOIN uploaded_files uf ON uf.id = ud.file_id
JOIN users u ON u.id = uf.user_id
WHERE ud.type = 'unmatched'
";

$params = [];
$types  = "";

if ($selectedUser !== '') {
    $sql .= " AND u.id = ? ";
    $params[] = $selectedUser;
    $types .= "i";
}
if ($selectedBank !== '') {
    $sql .= " AND uf.bank_name = ? ";
    $params[] = $selectedBank;
    $types .= "s";
}
if ($selectedStatus !== '') {
    $sql .= " AND ud.status = ? ";
    $params[] = $selectedStatus;
    $types .= "s";
}

$sql .= " ORDER BY ud.id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$rows   = $result->fetch_all(MYSQLI_ASSOC);


function parseDate($dateStr) {
    $dateStr = trim($dateStr);

    if (preg_match('/^\d{2}-[A-Z]{3}-\d{4}\d{2}:/i', $dateStr)) {
        $dateStr = substr($dateStr, 0, 11) . ' ' . substr($dateStr, 11);
    }

    $formats = [
        'd-M-Y H:i:s','d-M-Y H:i','d-M-Y','d-M-Y h:i:s A','d-M-Y h:i A',
        'd-m-Y H:i:s','d-m-Y H:i','d-m-Y',
        'Y-m-d H:i:s','Y-m-d H:i','Y-m-d',
        'm/d/Y H:i:s','m/d/Y H:i','m/d/Y',
        'd/m/Y H:i:s','d/m/Y H:i','d/m/Y',
        'd M Y H:i:s','d M Y H:i','d M Y'
    ];

    foreach ($formats as $f) {
        $d = DateTime::createFromFormat($f, $dateStr);
        if ($d !== false) return $d;
    }

    $ts = strtotime($dateStr);
    return $ts ? new DateTime("@$ts") : false;
}

$fromTs = $fromDate ? parseDate($fromDate) : null;
$toTs   = $toDate ? parseDate($toDate) : null;


$filteredRows = [];
foreach ($rows as $row) {
    $rowDate = parseDate($row['date']);
    if (!$rowDate) continue;

    if ($fromTs && $rowDate < $fromTs) continue;
    if ($toTs && $rowDate > $toTs) continue;

    $filteredRows[] = $row;
}

$totalUnmatched = count($filteredRows);


$groupedByBank = [];
$amountByBank  = [];

foreach ($filteredRows as $row) {
    $bank = $row['bank_name'] ?: 'Unknown Bank';
    $groupedByBank[$bank][] = $row;

    $amountByBank[$bank] =
        ($amountByBank[$bank] ?? 0) + floatval(str_replace(',', '', $row['amount']));
}


$allColumns = [
    'username'      => 'User',
    'holding_or_tl' => 'Holding / TL',
    'txn_id'        => 'Txn ID',
    'date'          => 'Date',
    'amount'        => 'Amount',
    'gateway'       => 'Gateway',
    'payment_type'  => 'Payment Type',
    'status'        => 'Status'
];
?>
<!DOCTYPE html>
<html>
<head>
<title>Admin - View Unmatched Data</title>
<style>
body { background:#f2f2f2; font-family:Arial; margin:0; }
.container { width:95%; margin:20px auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 5px 15px rgba(0,0,0,0.1); }
h2 { text-align:center; color:#1a73e8; margin-bottom:20px; }
.filter-box { display:flex; gap:10px; margin-bottom:20px; justify-content:center; flex-wrap:wrap; }
select, input[type="date"], button, a.reset { padding:10px; border-radius:6px; border:1px solid #ccc; font-size:14px; }
button { background:#1a73e8; color:#fff; border:none; cursor:pointer; font-weight:bold; }
button:hover { background:#1666c1; }
a.reset { background:#eee; text-decoration:none; color:#333; font-weight:bold; }
table { width:100%; border-collapse:collapse; margin-top:15px; }
th, td { border:1px solid #ddd; padding:10px; font-size:13px; }
th { background:#1a73e8; color:#fff; }
tr:nth-child(even) { background:#f9f9f9; }
.no-data { text-align:center; font-weight:bold; color:#888; margin-top:30px; }
.bank-title { margin-top:30px; font-weight:bold; font-size:16px; color:#1a73e8; }
.total-count { text-align:center; margin-bottom:20px; font-weight:bold; color:#1a73e8; }
</style>
</head>

<body>
<div class="container">
<h2>Unmatched Data (Admin Panel)</h2>

<form method="get">
<div class="filter-box">
<select name="user">
<option value="">-- All Users --</option>
<?php while($u = $usersRes->fetch_assoc()): ?>
<option value="<?= $u['id'] ?>" <?= ($selectedUser==$u['id'])?'selected':'' ?>>
    <?= htmlspecialchars($u['username']) ?>
</option>
<?php endwhile; ?>
</select>

<select name="bank">
<option value="">-- All Bank Portals --</option>
<?php while($b = $banksRes->fetch_assoc()): ?>
<option value="<?= htmlspecialchars($b['bank_name']) ?>" <?= ($selectedBank==$b['bank_name'])?'selected':'' ?>>
    <?= htmlspecialchars($b['bank_name']) ?>
</option>
<?php endwhile; ?>
</select>

<select name="status">
<option value="">-- All Status --</option>
<?php while($s = $statusRes->fetch_assoc()): ?>
<option value="<?= htmlspecialchars($s['status']) ?>" <?= ($selectedStatus==$s['status'])?'selected':'' ?>>
    <?= htmlspecialchars($s['status']) ?>
</option>
<?php endwhile; ?>
</select>

<input type="date" name="from_date" value="<?= htmlspecialchars($fromDate) ?>">
<input type="date" name="to_date" value="<?= htmlspecialchars($toDate) ?>">

<button type="submit">Filter</button>
<a href="view_unmatched.php" class="reset">Reset</a>
</div>
</form>

<div class="total-count">Total Unmatched Data: <?= $totalUnmatched ?></div>

<?php if (empty($groupedByBank)): ?>
    <div class="no-data">No unmatched data found.</div>
<?php else: ?>

<?php foreach ($groupedByBank as $bankName => $rowsByBank): ?>

<?php

$visibleColumns = [];
foreach ($allColumns as $key => $label) {
    foreach ($rowsByBank as $r) {
        if (!empty(trim($r[$key] ?? ''))) {
            $visibleColumns[$key] = $label;
            break;
        }
    }
}
?>

<div class="bank-title">
    <?= htmlspecialchars($bankName) ?><br>
    Total Unmatched Rows: (<?= count($rowsByBank) ?>)<br>
    Total Amount: <?= number_format($amountByBank[$bankName], 2) ?>
</div>

<table>
<tr>
<?php foreach ($visibleColumns as $label): ?>
    <th><?= htmlspecialchars($label) ?></th>
<?php endforeach; ?>
</tr>

<?php foreach ($rowsByBank as $r): ?>
<tr>
<?php foreach ($visibleColumns as $key => $label): ?>
    <td><?= htmlspecialchars($r[$key] ?? '') ?></td>
<?php endforeach; ?>
</tr>
<?php endforeach; ?>
</table>
<br>

<?php endforeach; ?>
<?php endif; ?>

</div>
</body>
</html>
