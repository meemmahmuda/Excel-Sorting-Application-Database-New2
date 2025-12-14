<?php
session_start();
include 'db.php';
include 'session.php';
include 'header.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: upload.php");
    exit();
}

/* ============================
   FILTER VALUES
============================ */
$selectedUser = $_GET['user'] ?? '';
$selectedBank = $_GET['bank'] ?? '';
$selectedStatus = $_GET['status'] ?? '';
$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';

/* ============================
   DROPDOWN DATA
============================ */
$usersRes = $conn->query("SELECT id, username FROM users ORDER BY username");
$banksRes = $conn->query("SELECT DISTINCT bank_name FROM uploaded_files ORDER BY bank_name");
$statusRes = $conn->query("SELECT DISTINCT status 
                           FROM uploaded_data 
                           WHERE type='unmatched' AND status IS NOT NULL AND status != '' 
                           ORDER BY status");

/* ============================
   FETCH UNMATCHED DATA
============================ */
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
$types = "";

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
$rows = $result->fetch_all(MYSQLI_ASSOC);

/* ============================
   PHP DATE PARSER FUNCTION
============================ */
function parseDate($dateStr) {
    $dateStr = trim($dateStr);
    
    // Fix missing space between date and time like 09-DEC-202513:01:57
    if (preg_match('/^\d{2}-[A-Z]{3}-\d{4}\d{2}:/i', $dateStr)) {
        $dateStr = substr($dateStr,0,11).' '.substr($dateStr,11);
    }

    $formats = [
        'd-M-Y H:i:s','d-M-Y H:i','d-M-Y','d-M-Y h:i:s A','d-M-Y h:i A',
        'd-m-Y H:i:s','d-m-Y H:i','d-m-Y',
        'Y-m-d H:i:s','Y-m-d H:i','Y-m-d',
        'm/d/Y H:i:s','m/d/Y H:i','m/d/Y',
        'd/m/Y H:i:s','d/m/Y H:i','d/m/Y',
        'd M Y H:i:s','d M Y H:i','d M Y',
    ];
    foreach ($formats as $f) {
        $d = DateTime::createFromFormat($f, $dateStr);
        if ($d !== false) return $d;
    }
    $ts = strtotime($dateStr);
    if ($ts !== false) return new DateTime("@$ts");
    return false;
}

// Convert filter dates
$fromTs = $fromDate ? parseDate($fromDate) : null;
$toTs = $toDate ? parseDate($toDate) : null;

// Filter rows by date range
$filteredRows = [];
foreach ($rows as $row) {
    $rowDate = parseDate($row['date']);
    if (!$rowDate) continue;
    $include = true;
    if ($fromTs && $rowDate < $fromTs) $include = false;
    if ($toTs && $rowDate > $toTs) $include = false;
    if ($include) $filteredRows[] = $row;
}

// Total unmatched count
$totalUnmatched = count($filteredRows);

// Group rows by bank and calculate total amount per bank
$groupedByBank = [];
$amountByBank = [];
foreach ($filteredRows as $row) {
    $bank = $row['bank_name'] ?: 'Unknown Bank';
    $groupedByBank[$bank][] = $row;
    $amountByBank[$bank] = ($amountByBank[$bank] ?? 0) + floatval(str_replace(',', '', $row['amount']));
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Admin - View Unmatched Data</title>
<style>
body { background:#f2f2f2; font-family:Arial; margin:0; }
.container { width:95%; margin:20px auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 5px 15px rgba(0,0,0,0.1);}
h2 { text-align:center; color:#1a73e8; margin-bottom:20px; }
.filter-box { display:flex; gap:10px; margin-bottom:20px; justify-content:center; flex-wrap:wrap;}
select, input[type="date"], button, a.reset { padding:10px; border-radius:6px; border:1px solid #ccc; font-size:14px; }
button { background:#1a73e8; color:#fff; border:none; cursor:pointer; font-weight:bold; }
button:hover { background:#1666c1; }
a.reset { background:#eee; text-decoration:none; color:#333; font-weight:bold; }
table { width:100%; border-collapse:collapse; margin-top:15px; }
th, td { border:1px solid #ddd; padding:10px; font-size:13px; text-align:left; }
th { background:#1a73e8; color:#fff; }
tr:nth-child(even) { background:#f9f9f9; }
.no-data { text-align:center; font-weight:bold; color:#888; margin-top:30px; }
.bank-title { margin-top:30px; font-weight:bold; font-size:16px; color:#1a73e8; }
.total-count { text-align:center; margin-bottom:20px; font-weight:bold; color:#1a73e8; }
.total-amount { text-align:right; font-weight:bold; margin-top:5px; color:#1a73e8; }
</style>
</head>
<body>
<div class="container">
<h2>Unmatched Data (Admin Panel)</h2>

<!-- FILTER FORM -->
<form method="get">
<div class="filter-box">
<select name="user">
<option value="">-- All Users --</option>
<?php while($u = $usersRes->fetch_assoc()): ?>
<option value="<?= $u['id'] ?>" <?= ($selectedUser==$u['id'])?'selected':'' ?>><?= htmlspecialchars($u['username']) ?></option>
<?php endwhile; ?>
</select>

<select name="bank">
<option value="">-- All Bank Portals --</option>
<?php while($b = $banksRes->fetch_assoc()): ?>
<option value="<?= htmlspecialchars($b['bank_name']) ?>" <?= ($selectedBank==$b['bank_name'])?'selected':'' ?>><?= htmlspecialchars($b['bank_name']) ?></option>
<?php endwhile; ?>
</select>

<select name="status">
<option value="">-- All Status --</option>
<?php while($s = $statusRes->fetch_assoc()): ?>
<option value="<?= htmlspecialchars($s['status']) ?>" <?= ($selectedStatus==$s['status'])?'selected':'' ?>><?= htmlspecialchars($s['status']) ?></option>
<?php endwhile; ?>
</select>

<input type="date" name="from_date" value="<?= htmlspecialchars($fromDate) ?>">
<input type="date" name="to_date" value="<?= htmlspecialchars($toDate) ?>">
<button type="submit">Filter</button>
<a href="view_unmatched.php" class="reset">Reset</a>
</div>
</form>

<!-- TOTAL COUNT -->
<div class="total-count">Total Unmatched Data: <?= $totalUnmatched ?></div>

<?php if(empty($groupedByBank)): ?>
    <div class="no-data">No unmatched data found.</div>
<?php else: ?>
    <?php foreach($groupedByBank as $bankName => $rowsByBank): ?>
        <div class="bank-title">
            <?= htmlspecialchars($bankName) ?><br>
            Total Unmatched Rows: (<?= count($rowsByBank) ?>)<br>
            Total Amount: <?= number_format($amountByBank[$bankName], 2) ?>
        </div>

        <table>
            <tr>
                <th>User</th>
                <th>Holding / TL</th>
                <th>Txn ID</th>
                <th>Date</th>
                <th>Amount</th>
                <th>Gateway</th>
                <th>Payment Type</th>
                <th>Status</th>
            </tr>
            <?php foreach($rowsByBank as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['username']) ?></td>
                <td><?= htmlspecialchars($r['holding_or_tl']) ?></td>
                <td><?= htmlspecialchars($r['txn_id']) ?></td>
                <td><?= htmlspecialchars($r['date']) ?></td>
                <td><?= htmlspecialchars($r['amount']) ?></td>
                <td><?= htmlspecialchars($r['gateway']) ?></td>
                <td><?= htmlspecialchars($r['payment_type']) ?></td>
                <td><?= htmlspecialchars($r['status']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <br>
    <?php endforeach; ?>
<?php endif; ?>
</div>
</body>
</html>
