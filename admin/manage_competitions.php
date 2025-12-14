<?php
session_start();
require_once __DIR__ . '/../database/db.php';
if (!isset($_SESSION['admin_id'])) { header('Location: admin_login.php'); exit; }

$msg = '';

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // IMPORTANT: Deleting a competition should ideally cascade and delete related data.
    // The database should have foreign key constraints with ON DELETE CASCADE.
    // For example: contestants, scores, criteria, competition_judges.
    $stmt = $conn->prepare("DELETE FROM competitions WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header('Location: manage_competitions.php?msg=' . urlencode('Competition deleted.'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create'])) {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $stmt = $conn->prepare("INSERT INTO competitions (name) VALUES (?)");
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $msg = 'Competition created successfully.';
        } else {
            $msg = 'Please provide a competition name.';
        }
    } elseif (isset($_POST['update'])) {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        if ($name !== '' && $id > 0) {
            $stmt = $conn->prepare("UPDATE competitions SET name = ? WHERE id = ?");
            $stmt->bind_param('si', $name, $id);
            $stmt->execute();
            $msg = 'Competition updated successfully.';
        } else {
            $msg = 'Update failed: Name cannot be empty.';
        }
    }
    header('Location: manage_competitions.php?msg=' . urlencode($msg));
    exit;
}

$competitions = $conn->query("SELECT * FROM competitions ORDER BY created_at DESC");
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Manage Competitions</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
  <div class="topbar">
    <div class="brand">Manage Competitions</div>
    <div class="links">
      <a href="admin_dashboard.php">Dashboard</a>
      <a href="../logout.php">Logout</a>
    </div>
  </div>
  <div class="wrapper">
    <div class="card">
      <div class="header-row"><h2>Competitions</h2></div>
      <?php if($msg): ?><p class="small" style="color:green;"><?=htmlspecialchars($msg)?></p><?php endif; ?>

      <form method="post" style="display:flex; gap:10px; align-items:center; margin-bottom: 20px;">
        <input class="input" type="text" name="name" placeholder="New Competition Name" required>
        <button class="btn" name="create" type="submit">Create Competition</button>
      </form>

      <table>
        <thead><tr><th>ID</th><th>Competition Name</th><th>Created On</th><th>Actions</th></tr></thead>
        <tbody>
          <?php while($comp = $competitions->fetch_assoc()): ?>
          <tr>
            <form method="post">
                <td><?= $comp['id'] ?><input type="hidden" name="id" value="<?= $comp['id'] ?>"></td>
                <td><input class="input" type="text" name="name" value="<?= htmlspecialchars($comp['name']) ?>"></td>
                <td><?= date('M d, Y', strtotime($comp['created_at'])) ?></td>
                <td class="table-actions">
                    <button class="btn" name="update" type="submit">Save</button>
                    <a href="manage_competitions.php?delete=<?= $comp['id'] ?>" onclick="return confirm('WARNING: Deleting a competition will also delete all of its contestants, judges, criteria, and scores. This cannot be undone. Are you sure?')" class="btn" style="background-color: #dc3545; color: white;">Delete</a>
                </td>
            </form>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>