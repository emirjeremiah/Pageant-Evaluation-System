<?php
session_start();
require_once __DIR__ . '/../database/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$active_competition_id = $_SESSION['active_competition_id'] ?? 1;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$name = $photo = '';
$age = $number = 0;

if ($id) {
    $q = $conn->prepare("SELECT id, name, age, number, photo FROM contestants WHERE id=? LIMIT 1");
    $q->bind_param('i', $id);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    if ($row) {
        $name = $row['name'];
        $age = $row['age'];
        $number = $row['number'];
        $photo = $row['photo'];
    } else {
        header('Location: manage_contestants.php');
        exit;
    }
}

$uploadDir = __DIR__ . '/../assets';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $age = (int)($_POST['age'] ?? 0);
    $number = (int)($_POST['number'] ?? 0);
    $current_photo = $_POST['current_photo'] ?? $photo; // Get existing photo name

    if (!empty($_FILES['photo']['name'])) {
        $f = $_FILES['photo'];
        $allowed = ['image/jpeg','image/png','image/webp'];
        if (in_array($f['type'], $allowed) && $f['size'] <= 2 * 1024 * 1024) {
            $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
            $newName = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            move_uploaded_file($f['tmp_name'], $uploadDir . '/' . $newName);
            if ($current_photo && file_exists($uploadDir . '/' . $current_photo)) unlink($uploadDir . '/' . $current_photo);
            $current_photo = $newName;
        }
    }

    if ($id) {
        $stmt = $conn->prepare("UPDATE contestants SET name=?, age=?, number=?, photo=? WHERE id=?");
        $stmt->bind_param('siisi', $name, $age, $number, $current_photo, $id);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO contestants (competition_id, name, age, number, photo) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('isiis', $active_competition_id, $name, $age, $number, $current_photo);
        $stmt->execute();
    }

    header('Location: manage_contestants.php');
    exit;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title><?= $id ? 'Edit' : 'Add' ?> Contestant</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
  <div class="topbar">
    <div class="brand"><?= $id ? 'Edit' : 'Add' ?> Contestant</div>
    <div class="links">
      <a href="manage_contestants.php">Back</a>
      <a href="../logout.php">Logout</a>
    </div>
  </div>

  <div class="wrapper">
    <div class="card">
      <form method="post" enctype="multipart/form-data" style="display:flex; gap:12px; flex-direction:column;">
        <label>Name</label>
        <input class="input" type="text" name="name" value="<?= htmlspecialchars($name) ?>" required>
        <label>Age</label>
        <input class="input" type="number" name="age" value="<?= htmlspecialchars($age) ?>" required>
        <label>Number</label>
        <input class="input" type="number" name="number" value="<?= htmlspecialchars($number) ?>" required>
        <label>Photo</label>
        <input type="file" name="photo" accept="image/*">
        <?php if ($photo && file_exists(__DIR__ . '/../assets/' . $photo)): ?>
          <input type="hidden" name="current_photo" value="<?= htmlspecialchars($photo) ?>">
          <img src="../assets/<?= htmlspecialchars($photo) ?>" style="width:120px;height:120px;object-fit:cover;border-radius:8px;">
        <?php endif; ?>
        <div style="display:flex; gap:8px; margin-top:10px;">
          <button class="btn" type="submit">Save</button>
          <a class="btn secondary" href="manage_contestants.php">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>