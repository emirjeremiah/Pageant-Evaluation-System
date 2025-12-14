<?php
require_once __DIR__ . '/database/db.php';

// Get the default competition ID (most recent) if none is selected
$default_competition_id = 0;
$default_comp_result = $conn->query("SELECT id FROM competitions ORDER BY created_at DESC LIMIT 1");
if ($default_comp_result && $default_comp_result->num_rows > 0) {
    $default_competition_id = $default_comp_result->fetch_assoc()['id'];
}

$competition_id = (int)($_GET['competition_id'] ?? $default_competition_id);

// Fetch all competitions for the dropdown
$all_competitions = $conn->query("SELECT id, name FROM competitions ORDER BY name ASC");

// Fetch contestants for the selected competition
$contestants_stmt = $conn->prepare("SELECT id, name, age, IFNULL(photo,'') AS photo FROM contestants WHERE competition_id = ? ORDER BY id ASC");
$contestants_stmt->bind_param("i", $competition_id);
$contestants_stmt->execute();
$res = $contestants_stmt->get_result();

// Fetch judges for the selected competition
$judges_stmt = $conn->prepare("SELECT j.name FROM judges j JOIN competition_judges cj ON j.id = cj.judge_id WHERE cj.competition_id = ? ORDER BY j.name ASC");
$judges_stmt->bind_param("i", $competition_id);
$judges_stmt->execute();
$res2 = $judges_stmt->get_result();

// Fetch and calculate REAL leaderboard scores for the selected competition
$leaderboard_stmt = $conn->prepare("SELECT con.name, con.photo, IFNULL(SUM(s.score * (cri.percentage / 100)) / COUNT(DISTINCT s.judge_id), 0) AS final_score FROM contestants con LEFT JOIN scores s ON con.id = s.contestant_id LEFT JOIN criteria cri ON s.criteria_id = cri.id WHERE con.competition_id = ? GROUP BY con.id, con.name, con.photo ORDER BY final_score DESC");
$leaderboard_stmt->bind_param("i", $competition_id);
$leaderboard_stmt->execute();
$leaderboard = $leaderboard_stmt->get_result();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Pageant Evaluation System</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="topbar">
    <div class="brand">Pageant Evaluation System</div>
    <form method="GET" id="public-competition-form" class="competition-selector" style="margin: 0 auto;">
        <label for="competition_id" style="color: var(--text); font-weight: 600;">View Competition:</label>
        <select name="competition_id" id="competition_id" class="input" onchange="this.form.submit();">
            <?php while($comp = $all_competitions->fetch_assoc()): ?>
                <option value="<?= $comp['id'] ?>" <?= $comp['id'] == $competition_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($comp['name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </form>
    <div class="links">
      <a href="login.php">Login</a>
    </div>
  </div>

  <div class="wrapper">
    <div class="grid">
      <main>
        <div class="card">
          <div class="header-row">
            <h2>Contestants</h2>
            <div class="small">Current Participants</div>
          </div>
          <?php if ($res && $res->num_rows): ?>
            <table>
              <thead><tr><th>Photo</th><th>Name</th><th>Age</th></tr></thead>
              <tbody>
                <?php while($r = $res->fetch_assoc()): 
                  $photo = (!empty($r['photo']) && file_exists(__DIR__ . '/assets/' . $r['photo'])) ? 'assets/' . $r['photo'] : '';
                ?>
                <tr>
                  <td class="td-photo"><?= $photo ? '<img src="'.htmlspecialchars($photo).'" class="thumb">' : '<div class="thumb placeholder"></div>' ?></td>
                  <td><?= htmlspecialchars($r['name']) ?></td>
                  <td><?= htmlspecialchars($r['age']) ?></td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p class="small">No contestants found.</p>
          <?php endif; ?>
        </div>

        <div class="card">
          <div class="header-row">
            <h2>Judges</h2>
            <div class="small">Official Panel</div>
          </div>
          <?php if ($res2 && $res2->num_rows): ?>
            <table>
              <thead><tr><th>Name</th></tr></thead>
              <tbody>
                <?php while($j = $res2->fetch_assoc()): ?>
                <tr><td><?= htmlspecialchars($j['name']) ?></td></tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p class="small">No judges available.</p>
          <?php endif; ?>
        </div>
      </main>

      <aside>
        <div class="card">
          <div class="header-row">
            <h2>Leaderboard</h2>
            <div class="small">Sample Rankings</div>
          </div>
          <?php if ($leaderboard && $leaderboard->num_rows): ?>
            <table class="leaderboard">
              <thead>
                <tr><th>Rank</th><th>Photo</th><th>Name</th><th>Score</th></tr>
              </thead>
              <tbody>
                <?php
                  $rank = 1;
                  while($l = $leaderboard->fetch_assoc()): 
                    $photo = (!empty($l['photo']) && file_exists(__DIR__ . '/assets/' . $l['photo'])) ? 'assets/' . $l['photo'] : '';
                ?>
                <tr class="rank-<?= $rank ?>">
                  <td>#<?= $rank++ ?></td>
                  <td><?= $photo ? '<img src="'.htmlspecialchars($photo).'" class="thumb-small">' : '<div class="thumb-small placeholder"></div>' ?></td>
                  <td><?= htmlspecialchars($l['name']) ?></td>
                  <td><strong><?= number_format($l['final_score'], 2) ?></strong></td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p class="small">No participants yet.</p>
          <?php endif; ?>
        </div>
      </aside>
    </div>
  </div>
</body>
</html>