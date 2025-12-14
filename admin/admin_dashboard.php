<?php
session_start();
require_once '../database/db.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// --- Competition Management ---
$all_competitions = $conn->query("SELECT id, name FROM competitions ORDER BY name ASC");

// Get the default competition ID (most recent) if none is selected
$default_competition_id = 0;
$default_comp_result = $conn->query("SELECT id FROM competitions ORDER BY created_at DESC LIMIT 1");
if ($default_comp_result && $default_comp_result->num_rows > 0) {
    $default_competition_id = $default_comp_result->fetch_assoc()['id'];
}

// Set active competition from POST request or session, or use the dynamic default
if (isset($_POST['competition_id'])) {
    $_SESSION['active_competition_id'] = (int)$_POST['competition_id'];
}
$active_competition_id = $_SESSION['active_competition_id'] ?? $default_competition_id;

// === KPI Section ===
$totalContestants_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM contestants WHERE competition_id = ?");
$totalContestants_stmt->bind_param("i", $active_competition_id);
$totalContestants_stmt->execute();
$totalContestants = $totalContestants_stmt->get_result()->fetch_assoc()['total'] ?? 0;

$totalJudges_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM competition_judges WHERE competition_id = ?");
$totalJudges_stmt->bind_param("i", $active_competition_id);
$totalJudges_stmt->execute();
$totalJudges = $totalJudges_stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Correctly calculate the overall weighted average score
$avgScore_stmt = $conn->prepare("SELECT SUM(s.score * (c.percentage / 100)) / COUNT(DISTINCT s.judge_id) AS avgScore FROM scores s JOIN criteria c ON s.criteria_id = c.id WHERE s.competition_id = ?");
$avgScore_stmt->bind_param("i", $active_competition_id);
$avgScore_stmt->execute();
$avgScoreResult = $avgScore_stmt->get_result()->fetch_assoc();
$averageScore = $avgScoreResult && $avgScoreResult['avgScore'] ? number_format($avgScoreResult['avgScore'], 2) : 'N/A';

// Top contestant
$topContestant_stmt = $conn->prepare("
    SELECT con.name, SUM(s.score * (cri.percentage / 100)) / COUNT(DISTINCT s.judge_id) AS final_score
    FROM scores s
    JOIN contestants con ON s.contestant_id = con.id
    JOIN criteria cri ON s.criteria_id = cri.id
    WHERE s.competition_id = ?
    GROUP BY con.id, con.name
    ORDER BY final_score DESC
    LIMIT 1
");
$topContestant_stmt->bind_param("i", $active_competition_id);
$topContestant_stmt->execute();
$topContestant = $topContestant_stmt->get_result()->fetch_assoc();
$topName = $topContestant['name'] ?? 'N/A';
$topAvg = isset($topContestant['final_score']) ? number_format($topContestant['final_score'], 2) : 'N/A';

// Leaderboard Preview (Top 5)
$leaderboard_stmt = $conn->prepare("
    SELECT con.name, IFNULL(SUM(s.score * (cri.percentage / 100)) / COUNT(DISTINCT s.judge_id), 0) AS final_score
    FROM contestants con
    LEFT JOIN scores s ON s.contestant_id = con.id
    LEFT JOIN criteria cri ON s.criteria_id = cri.id
    WHERE con.competition_id = ?
    GROUP BY con.id, con.name
    ORDER BY final_score DESC
    LIMIT 5
");
$leaderboard_stmt->bind_param("i", $active_competition_id);
$leaderboard_stmt->execute();
$leaderboard = $leaderboard_stmt->get_result();

// --- Notification Section ---
$notifications = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10");
$unread_count = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
  <div class="topbar admin-dashboard-topbar">
    <div class="brand">Pageant Admin Dashboard</div>
    <div class="links">
      <div class="topbar-section">
        <form method="POST" id="competition-form" class="competition-selector">
          <label for="competition_id">Active Competition:</label>
          <select name="competition_id" id="competition_id" class="input" onchange="this.form.submit();">
              <?php $all_competitions->data_seek(0); while($comp = $all_competitions->fetch_assoc()): ?>
                  <option value="<?= $comp['id'] ?>" <?= $comp['id'] == $active_competition_id ? 'selected' : '' ?>>
                      <?= htmlspecialchars($comp['name']) ?>
                  </option>
              <?php endwhile; ?>
          </select>
        </form>
      </div>
      <div class="topbar-section">
        <div class="dropdown-menu">
            <button class="btn" id="menu-button">Menu ▾</button>
            <div class="dropdown-content" id="menu-dropdown">
                <a href="manage_competitions.php">Manage Competitions</a>
                <a href="manage_contestants.php">Contestants</a>
                <a href="manage_judges.php">Judges</a>
                <a href="manage_criteria.php">Criteria</a>
                <a href="reports.php">Reports</a>
            </div>
        </div>
        <div class="notification-bell">
          <a href="#" id="bell-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2zM8 1.918l-.797.161A4.002 4.002 0 0 0 4 6c0 .628-.134 2.197-.459 3.742-.16.767-.376 1.566-.663 2.258h10.244c-.287-.692-.502-1.49-.663-2.258C12.134 8.197 12 6.628 12 6a4.002 4.002 0 0 0-3.203-3.92L8 1.917zM14.22 12c.223.447.481.801.78 1H1c.299-.199.557-.553.78-1C2.68 10.2 3 6.88 3 6c0-2.42 1.72-4.44 4.005-4.901a1 1 0 1 1 1.99 0A5.002 5.002 0 0 1 13 6c0 .88.32 4.2 1.22 6z"/></svg>
            <?php if ($unread_count > 0): ?>
              <span class="badge" id="notification-badge"><?= $unread_count ?></span>
            <?php endif; ?>
          </a>
          <div class="notification-dropdown" id="notification-dropdown">
            <div class="notification-header">Notifications</div>
            <?php if ($notifications->num_rows > 0): while($notif = $notifications->fetch_assoc()): ?>
              <div class="notification-item <?= $notif['is_read'] ? '' : 'unread' ?>"><?= $notif['message'] ?> <span class="time"><?= date('M d, g:i a', strtotime($notif['created_at'])) ?></span></div>
            <?php endwhile; else: ?><div class="notification-item">No notifications yet.</div><?php endif; ?>
          </div>
        </div>
        <a href="../logout.php">Logout</a>
      </div>
    </div>
  </div>

  <div class="wrapper dashboard">
    <h2>Dashboard Overview</h2>

    <div class="kpi-container">
      <div class="kpi-card">
        <h3><?= $totalContestants ?></h3>
        <p>Total Contestants</p>
      </div>
      <div class="kpi-card">
        <h3><?= $totalJudges ?></h3>
        <p>Total Judges</p>
      </div>
      <div class="kpi-card">
        <h3><?= $averageScore ?></h3>
        <p>Average Score</p>
      </div>
      <div class="kpi-card">
        <h3><?= htmlspecialchars($topName) ?></h3>
        <p>Top Contestant (<?= $topAvg ?>)</p>
      </div>
    </div>

    <div class="mini-leaderboard">
      <h3>Current Top 5 Contestants</h3>
      <table>
        <thead>
          <tr>
            <th>Rank</th>
            <th>Contestant</th>
            <th>Average Score</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          $rank = 1;
          while ($row = $leaderboard->fetch_assoc()): ?>
            <tr>
              <td><?= $rank++ ?></td>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><?= number_format($row['final_score'], 2) ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
      <a href="reports.php" class="btn">View Full Report →</a>
    </div>

    <script>
      document.addEventListener('DOMContentLoaded', function() {
        const bellIcon = document.getElementById('bell-icon');
        const dropdown = document.getElementById('notification-dropdown');
        const badge = document.getElementById('notification-badge');

        bellIcon.addEventListener('click', function(e) {
          e.preventDefault();
          dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';

          // If there's a badge and the dropdown is opened, mark notifications as read
          if (badge && dropdown.style.display === 'block') {
            fetch('mark_notifications_read.php')
              .then(response => response.json())
              .then(data => {
                if (data.success) {
                  badge.style.display = 'none'; // Hide badge
                  // Mark items as read visually
                  document.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.remove('unread');
                  });
                }
              });
          }
        });

        // Close dropdown if clicking outside
        document.addEventListener('click', function(e) {
          if (!bellIcon.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
          }
        });

        const menuButton = document.getElementById('menu-button');
        const menuDropdown = document.getElementById('menu-dropdown');

        menuButton.addEventListener('click', function(e) {
            e.preventDefault();
            menuDropdown.style.display = menuDropdown.style.display === 'block' ? 'none' : 'block';
        });

        document.addEventListener('click', function(e) {
            if (!menuButton.contains(e.target) && !menuDropdown.contains(e.target)) {
                menuDropdown.style.display = 'none';
            }
        });
      });
    </script>
  </div>
</body>
</html>