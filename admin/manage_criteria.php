<?php
session_start();
require_once __DIR__ . '/../database/db.php';
if (!isset($_SESSION['admin_id'])) { header('Location: admin_login.php'); exit; }

$active_competition_id = $_SESSION['active_competition_id'] ?? 1;

$msg = $_GET['msg'] ?? '';
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM criteria WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header('Location: manage_criteria.php');
    exit;
}

if (isset($_GET['delete_category'])) {
    $id = (int)$_GET['delete_category'];
    // Note: This will also delete criteria under this category due to DB constraints.
    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header('Location: manage_criteria.php?msg=' . urlencode('Category deleted.'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create'])) {
        $name = trim($_POST['name'] ?? '');
        $percentage = floatval($_POST['percentage'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);
        if ($name !== '' && $percentage > 0) {
            $stmt = $conn->prepare("INSERT INTO criteria (competition_id, name, percentage, category_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('isdi', $active_competition_id, $name, $percentage, $category_id);
            $stmt->execute();
            $msg = 'Criteria added.';
        } else $msg = 'Provide name and percentage.';
    } elseif (isset($_POST['update'])) {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $percentage = floatval($_POST['percentage'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);
        if ($name !== '' && $percentage > 0) {
            $stmt = $conn->prepare("UPDATE criteria SET name=?, percentage=?, category_id=? WHERE id=? AND competition_id = ?");
            $stmt->bind_param('sdiii', $name, $percentage, $category_id, $id, $active_competition_id);
            $stmt->execute();
            $msg = $stmt->affected_rows > 0 ? 'Criteria updated.' : 'Update failed or no changes made.';
        } else $msg = 'Provide name and percentage.';
    } elseif (isset($_POST['create_category'])) {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $msg = 'Category created successfully.';
        } else $msg = 'Provide a category name.';
    }
    header('Location: manage_criteria.php?msg=' . urlencode($msg));
    exit;
}

$categories = $conn->query("SELECT id, name FROM categories ORDER BY id ASC");
$res_stmt = $conn->prepare("SELECT id, name, percentage, IFNULL(category_id,0) AS category_id FROM criteria WHERE competition_id = ? ORDER BY id ASC");
$res_stmt->bind_param("i", $active_competition_id);
$res_stmt->execute();
$res = $res_stmt->get_result();
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Manage Criteria</title><link rel="stylesheet" href="../style.css"></head>
<body>
  <div class="topbar">
    <div class="brand">Manage Criteria</div>
    <div class="links">
      <a href="admin_dashboard.php">Dashboard</a>
      <a href="../logout.php">Logout</a>
    </div>
  </div>
  <div class="wrapper">
    <div class="card">
        <div class="header-row"><h2>Manage Categories</h2></div>        <?php if($msg && str_contains($msg, 'Category')): ?><p class="small" style="color:green;"><?=htmlspecialchars($msg)?></p><?php endif; ?>
        <form method="post" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom: 20px;">
            <input class="input" type="text" name="name" placeholder="New Category Name" required>
            <button class="btn" name="create_category" type="submit">Create Category</button>
        </form>
        <table style="margin-bottom: 40px;">
            <thead><tr><th>ID</th><th>Category Name</th><th>Actions</th></tr></thead>
            <tbody>
                <?php $categories->data_seek(0); while($cat = $categories->fetch_assoc()): ?>
                <tr>
                    <td><?= $cat['id'] ?></td>
                    <td><?= htmlspecialchars($cat['name']) ?></td>
                    <td class="table-actions"><a href="manage_criteria.php?delete_category=<?= $cat['id'] ?>" onclick="return confirm('WARNING: Deleting a category will also delete all criteria within it. Proceed?')">Delete</a></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-top: 30px;">
      <div class="header-row"><h2>Criteria</h2><div class="small">Create or adjust criteria weights</div></div>
      <?php if($msg && str_contains($msg, 'Criteria')): ?><p class="small" style="color:green;"><?=htmlspecialchars($msg)?></p><?php endif; ?>

      <form method="post" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <input class="input" type="text" name="name" placeholder="Criteria Name">
        <input class="input" type="number" name="percentage" placeholder="Percentage e.g. 40.00" step="0.01" style="max-width:180px;">
        <select class="input" name="category_id" style="max-width:200px;">
          <option value="0">-- Select category --</option>
          <?php
            $cats = [];
            $categories->data_seek(0);
            while ($cat = $categories->fetch_assoc()) { $cats[$cat['id']] = $cat['name']; echo "<option value=\"{$cat['id']}\">".htmlspecialchars($cat['name'])."</option>"; }
          ?>
        </select>
        <button class="btn" name="create" type="submit">Add</button>
      </form>

      <div style="margin-top:14px;">
        <table>
          <thead><tr><th>ID</th><th>Name</th><th>Percentage</th><th>Category</th><th>Actions</th></tr></thead>
          <tbody>
            <?php while($c = $res->fetch_assoc()): ?>
            <tr>
              <form method="post">
                <td><?= $c['id'] ?><input type="hidden" name="id" value="<?= $c['id'] ?>"></td>
                <td><input class="input" type="text" name="name" value="<?=htmlspecialchars($c['name'])?>"></td>
                <td><input class="input" type="number" name="percentage" value="<?=htmlspecialchars($c['percentage'])?>" step="0.01" style="max-width:120px;"></td>
                <td>
                  <select class="input" name="category_id" style="max-width:220px;">
                    <option value="0">-- Select --</option>
                    <?php foreach ($cats as $cid => $cname): ?>
                      <option value="<?= $cid ?>" <?= ($c['category_id'] == $cid) ? 'selected' : '' ?>><?= htmlspecialchars($cname) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td class="table-actions">
                  <button class="btn" name="update" type="submit">Save</button>
                  <a href="manage_criteria.php?delete=<?= $c['id'] ?>" onclick="return confirm('Delete?')">Delete</a>
                </td>
              </form>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>

    </div>
  </div>
</body>
</html>