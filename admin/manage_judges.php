<?php
session_start();
require_once __DIR__ . '/../database/db.php';
if (!isset($_SESSION['admin_id'])) { header('Location: admin_login.php'); exit; }

$active_competition_id = $_SESSION['active_competition_id'] ?? 1;
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle creating a new judge
    if (isset($_POST['create_judge'])) {
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($name && $username && $password) {
            $stmt = $conn->prepare("INSERT INTO judges (name, username, password) VALUES (?, ?, ?)");
            $stmt->bind_param('sss', $name, $username, $password);
            if ($stmt->execute()) {
                // Automatically assign the new judge to the current competition
                $new_judge_id = $conn->insert_id;
                $assign_stmt = $conn->prepare("INSERT INTO competition_judges (competition_id, judge_id) VALUES (?, ?)");
                $assign_stmt->bind_param('ii', $active_competition_id, $new_judge_id);
                $assign_stmt->execute();
            }
            $msg = 'Judge created successfully.';
        } else {
            $msg = 'Please fill all fields to create a judge.';
        }
    }

    // Handle updating judge assignments for the current competition
    if (isset($_POST['update_assignments'])) {
        $assigned_judges = $_POST['judges'] ?? [];

        // First, remove all existing assignments for this competition
        $delete_stmt = $conn->prepare("DELETE FROM competition_judges WHERE competition_id = ?");
        $delete_stmt->bind_param('i', $active_competition_id);
        $delete_stmt->execute();

        // Now, insert the new assignments
        if (!empty($assigned_judges)) {
            $insert_stmt = $conn->prepare("INSERT INTO competition_judges (competition_id, judge_id) VALUES (?, ?)");
            foreach ($assigned_judges as $judge_id) {
                $insert_stmt->bind_param('ii', $active_competition_id, $judge_id);
                $insert_stmt->execute();
            }
        }
        $msg = 'Judge assignments updated for this competition.';
    }
}

// Fetch all judges
$all_judges = $conn->query("SELECT id, name, username FROM judges ORDER BY name ASC");

// Fetch judges already assigned to the active competition
$assigned_judges_stmt = $conn->prepare("SELECT judge_id FROM competition_judges WHERE competition_id = ?");
$assigned_judges_stmt->bind_param('i', $active_competition_id);
$assigned_judges_stmt->execute();
$assigned_judges_res = $assigned_judges_stmt->get_result();
$assigned_judge_ids = [];
while ($row = $assigned_judges_res->fetch_assoc()) {
    $assigned_judge_ids[] = $row['judge_id'];
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Manage Judges</title><link rel="stylesheet" href="../style.css"></head>
<body>
  <div class="topbar"><div class="brand">Manage Judges</div><div class="links"><a href="admin_dashboard.php">Dashboard</a><a href="../logout.php">Logout</a></div></div>
  <div class="wrapper">
    <div class="card">
      <div class="header-row"><h2>Create New Judge</h2></div>
      <form method="post" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom: 30px;">
        <input class="input" type="text" name="name" placeholder="Judge's Full Name" required>
        <input class="input" type="text" name="username" placeholder="Username" required>
        <input class="input" type="text" name="password" placeholder="Password" required>
        <button class="btn" name="create_judge" type="submit">Create Judge</button>
      </form>

      <div class="header-row"><h2>Assign Judges to Competition</h2></div>
      <?php if($msg): ?><p class="small" style="color:green;"><?=htmlspecialchars($msg)?></p><?php endif; ?>
      <form method="post">
        <table>
          <thead><tr><th style="width:50px;">Assign</th><th>Judge Name</th><th>Username</th></tr></thead>
          <tbody>
            <?php while($judge = $all_judges->fetch_assoc()): ?>
            <tr>
              <td><input type="checkbox" name="judges[]" value="<?= $judge['id'] ?>" <?= in_array($judge['id'], $assigned_judge_ids) ? 'checked' : '' ?>></td>
              <td><?= htmlspecialchars($judge['name']) ?></td>
              <td><?= htmlspecialchars($judge['username']) ?></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
        <button class="btn" name="update_assignments" type="submit" style="margin-top:15px;">Save Assignments</button>
      </form>
    </div>
  </div>
</body>
</html>