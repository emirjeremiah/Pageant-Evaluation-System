<?php
session_start();
require_once __DIR__ . '/../database/db.php';
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$active_competition_id = $_SESSION['active_competition_id'] ?? 1;

$msg = $_GET['msg'] ?? ''; // Read message from URL
$uploadDir = __DIR__ . '/../assets';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $photo_to_delete_stmt = $conn->prepare("SELECT photo FROM contestants WHERE id = ?");
    $photo_to_delete_stmt->bind_param('i', $id);
    $photo_to_delete_stmt->execute();
    $photo_res = $photo_to_delete_stmt->get_result()->fetch_assoc();

    if ($photo_res && $photo_res['photo']) {
        $photo_filename = $photo_res['photo'];
        // Check if any OTHER contestant is using this photo
        $check_usage_stmt = $conn->prepare("SELECT COUNT(*) as count FROM contestants WHERE photo = ? AND id != ?");
        $check_usage_stmt->bind_param('si', $photo_filename, $id);
        $check_usage_stmt->execute();
        $usage_count = $check_usage_stmt->get_result()->fetch_assoc()['count'];

        // Only delete the file if no other contestant is using it
        if ($usage_count == 0) {
            $photo_path = $uploadDir . '/' . $photo_filename;
            if (file_exists($photo_path)) {
                unlink($photo_path);
            }
        }
    }
    $stmt = $conn->prepare("DELETE FROM contestants WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header('Location: manage_contestants.php?msg=' . urlencode('Contestant deleted.'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    $name = trim($_POST['name'] ?? '');
    $age = (int)($_POST['age'] ?? 0);
    $photoName = null;

    if (!empty($_FILES['photo']['name'])) {
        $f = $_FILES['photo'];
        $allowed = ['image/jpeg','image/png','image/webp'];
        if (in_array($f['type'], $allowed) && $f['size'] <= 2 * 1024 * 1024) {
            $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
            $photoName = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            move_uploaded_file($f['tmp_name'], $uploadDir . '/' . $photoName);
        } else {
            $msg = 'Invalid photo or too large.';
        }
    }

    if ($name !== '') {
        // Get the next available contestant number
        $nextNumStmt = $conn->prepare("SELECT IFNULL(MAX(number), 0) + 1 AS next_num FROM contestants WHERE competition_id = ?");
        $nextNumStmt->bind_param("i", $active_competition_id);
        $nextNumStmt->execute();
        $nextNumber = $nextNumStmt->get_result()->fetch_assoc()['next_num'];

        $stmt = $conn->prepare("INSERT INTO contestants (competition_id, name, age, number, photo) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('isiis', $active_competition_id, $name, $age, $nextNumber, $photoName);
        $stmt->execute();
        $msg = 'Contestant added.';
    } else {
        $msg = 'Provide a name.';
    }
    header('Location: manage_contestants.php?msg=' . urlencode($msg));
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_contestants'])) {
    $contestant_ids_to_import = $_POST['contestants'] ?? [];
    if (!empty($contestant_ids_to_import)) {
        $ids_placeholder = implode(',', array_fill(0, count($contestant_ids_to_import), '?'));
        $source_contestants_stmt = $conn->prepare("SELECT name, age, photo FROM contestants WHERE id IN ($ids_placeholder)");
        $types = str_repeat('i', count($contestant_ids_to_import));
        $source_contestants_stmt->bind_param($types, ...$contestant_ids_to_import);
        $source_contestants_stmt->execute();
        $source_contestants = $source_contestants_stmt->get_result();

        $insert_stmt = $conn->prepare("INSERT INTO contestants (competition_id, name, age, number, photo) VALUES (?, ?, ?, ?, ?)");
        while ($contestant = $source_contestants->fetch_assoc()) {
            $nextNumStmt = $conn->prepare("SELECT IFNULL(MAX(number), 0) + 1 AS next_num FROM contestants WHERE competition_id = ?");
            $nextNumStmt->bind_param("i", $active_competition_id);
            $nextNumStmt->execute();
            $nextNumber = $nextNumStmt->get_result()->fetch_assoc()['next_num'];
            $insert_stmt->bind_param('isiis', $active_competition_id, $contestant['name'], $contestant['age'], $nextNumber, $contestant['photo']);
            $insert_stmt->execute();
        }
        $msg = count($contestant_ids_to_import) . ' contestant(s) imported successfully.';
    } else {
        $msg = 'No contestants selected to import.';
    }
    header('Location: manage_contestants.php?msg=' . urlencode($msg));
    exit;
}

$stmt = $conn->prepare("SELECT id, name, age, photo FROM contestants WHERE competition_id = ? ORDER BY id ASC");
$stmt->bind_param("i", $active_competition_id);
$stmt->execute();
$res = $stmt->get_result();

// Fetch contestants from other competitions to allow importing
$import_stmt = $conn->prepare("SELECT id, name FROM contestants WHERE competition_id != ?");
$import_stmt->bind_param("i", $active_competition_id);
$import_stmt->execute();
$import_res = $import_stmt->get_result();

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Manage Contestants</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
  <div class="topbar">
    <div class="brand">Manage Contestants</div>
    <div class="links">
      <a href="admin_dashboard.php">Dashboard</a>
      <a href="../logout.php">Logout</a>
    </div>
  </div>

  <div class="wrapper">
    <div class="card">
      <div class="header-row">
        <h2>Current Contestants</h2>
        <div class="small">Create, edit or remove contestants</div>
      </div>

      <?php if($msg): ?><p class="small" style="color:green;"><?=htmlspecialchars($msg)?></p><?php endif; ?>

      <form method="post" enctype="multipart/form-data" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <input class="input" type="text" name="name" placeholder="Name">
        <input class="input" type="number" name="age" placeholder="Age" style="max-width:120px;">
        <input type="file" name="photo" accept="image/*">
        <button class="btn" name="create" type="submit">Add Contestant</button>
      </form>

      <div style="margin-top:14px;">
        <table>
          <thead><tr><th>ID</th><th>Photo</th><th>Name</th><th>Age</th><th>Actions</th></tr></thead>
          <tbody>
            <?php while($c = $res->fetch_assoc()): ?>
              <tr>
                <td><?= $c['id'] ?></td>
                <td>
                  <?php if($c['photo'] && file_exists(__DIR__ . '/../assets/' . $c['photo'])): ?>
                    <img src="../assets/<?=htmlspecialchars($c['photo'])?>" style="width:64px;height:48px;object-fit:cover;border-radius:6px;">
                  <?php else: ?>-<?php endif; ?>
                </td>
                <td><?=htmlspecialchars($c['name'])?></td>
                <td><?=htmlspecialchars($c['age'])?></td>
                <td class="table-actions">
                  <a href="admin_edit_contestant.php?id=<?= $c['id'] ?>">Edit</a> |
                  <a href="manage_contestants.php?delete=<?= $c['id'] ?>" onclick="return confirm('Delete this contestant?')">Delete</a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($import_res->num_rows > 0): ?>
    <div class="card" style="margin-top: 30px;">
        <div class="header-row"><h2>Import Existing Contestants</h2></div>
        <p class="small">Select contestants from other competitions to add them to this one.</p>
        <form method="post">
            <table>
                <thead><tr><th style="width:50px;">Import</th><th>Contestant Name</th></tr></thead>
                <tbody>
                    <?php while($import_c = $import_res->fetch_assoc()): ?>
                    <tr>
                        <td><input type="checkbox" name="contestants[]" value="<?= $import_c['id'] ?>"></td>
                        <td><?= htmlspecialchars($import_c['name']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <button class="btn" name="import_contestants" type="submit" style="margin-top:15px;">Import Selected</button>
        </form>
    </div>
    <?php endif; ?>
  </div>
</body>
</html>