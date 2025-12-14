<?php
session_start();
require_once __DIR__ . '/database/db.php';
if (!isset($_SESSION['admin_id'])) { header('Location: admin_login.php'); exit; }

// Get the default competition ID (most recent) if none is selected
$default_competition_id = 0;
$default_comp_result = $conn->query("SELECT id FROM competitions ORDER BY created_at DESC LIMIT 1");
if ($default_comp_result && $default_comp_result->num_rows > 0) {
    $default_competition_id = $default_comp_result->fetch_assoc()['id'];
}

$active_competition_id = $_SESSION['active_competition_id'] ?? $default_competition_id;

$list_stmt = $conn->prepare("SELECT con.name, con.photo, IFNULL(SUM(s.score * (cri.percentage / 100)) / COUNT(DISTINCT s.judge_id), 0) AS final_score FROM contestants con LEFT JOIN scores s ON con.id = s.contestant_id LEFT JOIN criteria cri ON s.criteria_id = cri.id WHERE con.competition_id = ? GROUP BY con.id, con.name, con.photo ORDER BY final_score DESC");
$list_stmt->bind_param("i", $active_competition_id);
$list_stmt->execute();
$list = $list_stmt->get_result();

// CSV download
if (isset($_GET['download']) && $_GET['download']=='csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=leaderboard.csv');
    $out = fopen('php://output','w');
    fputcsv($out,['Rank','Contestant','Final Score']);
    $rank = 1;
    while ($row = $list->fetch_assoc()) {
        fputcsv($out, [$rank, $row['name'], number_format($row['final_score'], 2)]);
        $rank++;
    }
    fclose($out);
    exit;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Leaderboard / Reports</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="topbar">
    <div class="brand">Leaderboard</div>
    <div class="links">
      <a href="admin/admin_dashboard.php">Admin</a>
      <a href="admin/judge_reports.php">Judge Reports</a>
      <a href="index.php">Home</a>
    </div>
  </div>
  <div class="wrapper">
    <div class="card header-row">
      <h2>Final Rankings</h2>
      <div class="actions">
        <a class="btn" href="?download=csv">Download CSV</a>
        <button class="btn" onclick="window.print()">Print</button>
      </div>
    </div>

    <div class="card">
      <table class="leaderboard">
        <thead><tr><th>Rank</th><th>Contestant</th><th>Photo</th><th>Final Score</th></tr></thead>
        <tbody>
          <?php $r=1; $list->data_seek(0); while($row = $list->fetch_assoc()): ?>
            <tr class="rank-<?= $r ?>">
              <td>#<?= $r ?></td>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td>
                <?php if(!empty($row['photo']) && file_exists(__DIR__ . '/assets/' . $row['photo'])): ?>
                  <img src="assets/<?=htmlspecialchars($row['photo'])?>" class="thumb-small">
                <?php else: ?>
                  <div class="thumb-small placeholder"></div>
                <?php endif; ?>
              </td>
              <td><strong><?= number_format($row['final_score'], 2) ?></strong></td>
            </tr>
          <?php $r++; endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>