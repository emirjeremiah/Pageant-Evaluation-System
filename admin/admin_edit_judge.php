<?php
session_start();
require_once __DIR__ . '/../database/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$name = $username = '';

if ($id) {
    $q = $conn->prepare("SELECT id, name, username FROM judges WHERE id=? LIMIT 1");
    $q->bind_param('i', $id);
    $q->execute();
    $r = $q->get_result()->fetch_assoc();
    if ($r) {
        $name = $r['name'];
        $username = $r['username'];
    } else {
        header('Location: manage_judges.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');

    if (!empty($_POST['password'])) {
        $password = $_POST['password'];
        $stmt = $conn->prepare("UPDATE judges SET name=?, username=?, password=? WHERE id=?");
        $stmt->bind_param('sssi', $name, $username, $password, $id);
    } else {
        $stmt = $conn->prepare("UPDATE judges SET name=?, username=? WHERE id=?");
        $stmt->bind_param('ssi', $name, $username, $id);
    }
    $stmt->execute();

    header('Location: manage_judges.php');
    exit;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Edit Judge</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
  <div class="topbar">
    <div class="brand">Edit Judge</div>
    <div class="links">
      <a href="manage_judges.php">Back</a>
      <a href="../logout.php">Logout</a>
    </div>
  </div>

  <div class="wrapper">
    <div class="card">
      <form method="post" style="display:flex; gap:12px; flex-direction:column;">
        <label>Full name</label>
        <input class="input" type="text" name="name" value="<?= htmlspecialchars($name) ?>" required>
        <label>Username</label>
        <input class="input" type="text" name="username" value="<?= htmlspecialchars($username) ?>" required>
        <label>New password (leave blank to keep)</label>
        <input class="input" type="password" name="password">
        <div style="display:flex; gap:8px;">
          <button class="btn" type="submit">Save</button>
          <a class="btn secondary" href="manage_judges.php">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>