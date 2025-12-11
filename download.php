<?php
session_start();
include 'db.php';
include 'session.php'; // protect page
include 'header.php';
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Ensure user is logged in
$userId = $_SESSION['user_id'] ?? 0;
if(!$userId){
    header("Location: login.php");
    exit();
}

// ------------------------
// Download Excel function
// ------------------------
function cleanRows($rows){
    if(empty($rows)) return [];
    foreach($rows as &$row){
        unset($row['id'], $row['file_id']);
        if(!isset($row['status'])) $row['status'] = '';
    }
    return $rows;
}

function downloadExcel($rows, $filename){
    if(empty($rows)) return;
    $rows = cleanRows($rows);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->fromArray(array_keys(reset($rows)), null, 'A1');
    $sheet->fromArray(array_map('array_values', $rows), null, 'A2');

    if(ob_get_length()) ob_end_clean(); // prevent corruption
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}

// ------------------------
// Handle download
// ------------------------
if(isset($_GET['download_file'])){
    $fileId = intval($_GET['download_file']);
    $stmt = $conn->prepare("SELECT * FROM uploaded_files WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $fileId, $userId);
    $stmt->execute();
    $res = $stmt->get_result();

    if($row = $res->fetch_assoc()){
        $stmt2 = $conn->prepare("SELECT * FROM uploaded_data WHERE file_id=?");
        $stmt2->bind_param("i", $fileId);
        $stmt2->execute();
        $dataRes = $stmt2->get_result();

        $rows = [];
        while ($r = $dataRes->fetch_assoc()) $rows[] = $r;

        downloadExcel($rows, $row['filename']);
    } else {
        die("File not found or access denied.");
    }
}

// ------------------------
// Handle delete
// ------------------------
if(isset($_GET['delete_file'])){
    $fileId = intval($_GET['delete_file']);

    $stmt = $conn->prepare("DELETE FROM uploaded_files WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $fileId, $userId);
    $stmt->execute();

    $stmt2 = $conn->prepare("DELETE FROM uploaded_data WHERE file_id=?");
    $stmt2->bind_param("i", $fileId);
    $stmt2->execute();

    header("Location: download.php");
    exit();
}

// ------------------------
// Pagination settings
// ------------------------
$limit = 5;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Total files count for current user
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM uploaded_files WHERE user_id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$totalRes = $stmt->get_result();
$totalFiles = $totalRes->fetch_assoc()['total'];
$totalPages = ceil($totalFiles / $limit);

// Fetch current page files for current user
$allFiles = [];
$stmt = $conn->prepare("SELECT * FROM uploaded_files WHERE user_id=? ORDER BY id DESC LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $userId, $limit, $offset);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) $allFiles[] = $row;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Download Files</title>
    <style>
        /* Basic styling */
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, sans-serif; }
        body { background: #f2f2f2; padding: 0; margin: 0; font-family: Arial, sans-serif; }
        table { border-collapse: collapse; width: 90%; margin: 20px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        th, td { border-bottom: 1px solid #ddd; padding: 12px 15px; text-align: left; }
        th { background-color: #1a73e8; color: #fff; font-weight: bold; }
        tr:hover { background-color: #f1f1f1; }
        .actions button { background: #1a73e8; color: #fff; border: none; border-radius: 5px; padding: 6px 12px; cursor: pointer; font-size: 14px; font-weight: bold; margin-right: 5px; transition: 0.3s; }
        .actions button:hover { background: #1666c1; }
        .actions .delete-btn { background: #d93025; }
        .actions .delete-btn:hover { background: #a61c1c; }
        .pagination { text-align: center; margin: 20px 0; }
        .pagination a { display: inline-block; padding: 8px 12px; margin: 0 4px; text-decoration: none; color: #1a73e8; border-radius: 4px; border: 1px solid #1a73e8; transition: 0.3s; }
        .pagination a.current, .pagination a:hover { background: #1a73e8; color: #fff; }
        .upload-btn { display: block; width: 200px; margin: 20px auto; padding: 12px; text-align: center; background: #1a73e8; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold; transition: 0.3s; }
        .upload-btn:hover { background: #1666c1; }
    </style>
</head>
<body>

<!-- <h2>All Uploaded Files</h2> -->
<br>
<table>
<tr>
    <th>Filename</th>
    <th>Type</th>
    <th>Bank</th>
    <th>Uploaded Time</th>
    <th>Actions</th>
</tr>
<?php foreach($allFiles as $file): ?>
<tr>
    <td><?= htmlspecialchars($file['filename']) ?></td>
    <td><?= htmlspecialchars($file['type']) ?></td>
    <td><?= htmlspecialchars($file['bank_name']) ?></td>
    <td><?= htmlspecialchars($file['uploaded_at']) ?></td>
    <td class="actions">
        <a href="view_file.php?file_id=<?= $file['id'] ?>"><button>View</button></a>
        <a href="download.php?download_file=<?= $file['id'] ?>"><button>Download</button></a>
        <a href="download.php?delete_file=<?= $file['id'] ?>" onclick="return confirm('Are you sure?')"><button class="delete-btn">Delete</button></a>
    </td>
</tr>
<?php endforeach; ?>
</table>

<!-- Pagination -->
<div class="pagination">
    <?php if($page > 1): ?>
        <a href="?page=<?= $page-1 ?>">&laquo; Previous</a>
    <?php endif; ?>
    <?php for($p=1; $p<=$totalPages; $p++): ?>
        <a href="?page=<?= $p ?>" class="<?= $p==$page?'current':'' ?>"><?= $p ?></a>
    <?php endfor; ?>
    <?php if($page < $totalPages): ?>
        <a href="?page=<?= $page+1 ?>">Next &raquo;</a>
    <?php endif; ?>
</div>

<a href="upload.php" class="upload-btn">Upload More Files</a>

</body>
</html>
