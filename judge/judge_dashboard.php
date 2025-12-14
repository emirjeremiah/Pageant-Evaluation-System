<?php
session_start();
// A judge must be logged in and have a competition selected
if (!isset($_SESSION['judge_id']) || !isset($_SESSION['competition_id'])) {
    header("Location: judge_login.php");
    exit();
}

require_once __DIR__ . '/../database/db.php';
$judge_id = $_SESSION['judge_id'];
$competition_id = $_SESSION['competition_id'];

$criteria_query = "
    SELECT cat.name AS category_name, cri.id, cri.name, cri.percentage
    FROM criteria cri
    JOIN categories cat ON cri.category_id = cat.id
    WHERE cri.competition_id = ?
    ORDER BY cat.id, cri.id
";
$criteria_stmt = $conn->prepare($criteria_query);
$criteria_stmt->bind_param("i", $competition_id);
$criteria_stmt->execute();
$criteria_res = $criteria_stmt->get_result();
$grouped_criteria = [];
while ($row = $criteria_res->fetch_assoc()) {
    $grouped_criteria[$row['category_name']][] = $row;
}

// --- New Queries for Dashboard Widgets ---
// Get total number of contestants
$contestant_count_stmt = $conn->prepare("SELECT COUNT(id) as total FROM contestants WHERE competition_id = ?");
$contestant_count_stmt->bind_param("i", $competition_id);
$contestant_count_stmt->execute();
$contestant_count = $contestant_count_stmt->get_result()->fetch_assoc()['total'];

// Get total number of criteria
$criteria_count = $criteria_res->num_rows;

// Get leaderboard data
$leaderboard_stmt = $conn->prepare("
    SELECT
        con.name,
        con.photo,
        SUM(s.score * (cri.percentage / 100.0)) AS final_score
    FROM scores s
    JOIN contestants con ON s.contestant_id = con.id
    JOIN criteria cri ON s.criteria_id = cri.id
    WHERE s.judge_id = ? AND s.competition_id = ?
    GROUP BY con.id, con.name, con.photo
    ORDER BY final_score DESC
");
$leaderboard_stmt->bind_param("ii", $judge_id, $competition_id);
$leaderboard_stmt->execute();
$leaderboard_res = $leaderboard_stmt->get_result();

// Determine the active criteria ID. Default to the first criteria of the first group.
$first_category = !empty($grouped_criteria) ? reset($grouped_criteria) : [];
$first_criterion = !empty($first_category) ? reset($first_category) : [];
$active_criteria_id = $_GET['criteria_id'] ?? ($first_criterion['id'] ?? null);

if ($active_criteria_id) {
    $contestants_stmt = $conn->prepare("SELECT * FROM contestants WHERE competition_id = ? ORDER BY id ASC");
    $contestants_stmt->bind_param("i", $competition_id);
    $contestants_stmt->execute();
    $contestants_res = $contestants_stmt->get_result();
    if (!$contestants_res) { // This check is less likely to fail with prepared statements but good to keep
        die("Error fetching contestants: " . $conn->error);
    }

    $scores_res = $conn->prepare("SELECT contestant_id, score FROM scores WHERE judge_id = ? AND criteria_id = ? AND competition_id = ?");
    $scores_res->bind_param("iii", $judge_id, $active_criteria_id, $competition_id);
    $scores_res->execute();
    $scores_result = $scores_res->get_result();
    $scores = [];
    while ($row = $scores_result->fetch_assoc()) {
        $scores[$row['contestant_id']] = $row['score'];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Judge Dashboard - Pageant Evaluation System</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="topbar">
        <div class="brand">Judge Dashboard</div>
        <div class="links">
            <span>Welcome, <?= htmlspecialchars($_SESSION['judge_username']) ?>!</span>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="wrapper">
        <div class="grid">
            <main>
                <div class="card">
                    <div class="header-row">
                        <h2>Contestants Scoring</h2>
                    </div>

                    <?php if (isset($_GET['success'])): ?>
                        <p class="success-msg">Scores submitted successfully!</p>
                    <?php endif; ?>

                    <div class="criteria-groups">
                        <?php foreach ($grouped_criteria as $category_name => $criteria_list): ?>
                            <div class="category-group">
                                <h4 class="category-title"><?= htmlspecialchars($category_name) ?></h4>
                                <div class="tabs">
                                    <?php foreach ($criteria_list as $c): ?>
                                        <a href="?criteria_id=<?= $c['id'] ?>" class="<?= $c['id'] == $active_criteria_id ? 'active' : '' ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['percentage']) ?>%)</a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($active_criteria_id && $contestants_res->num_rows > 0): ?>
                        <form action="submit_score.php" method="POST">
                            <input type="hidden" name="judge_id" value="<?= $judge_id ?>">
                            <input type="hidden" name="criteria_id" value="<?= $active_criteria_id ?>">
                            <input type="hidden" name="competition_id" value="<?= $competition_id ?>">
                            <table class="table">
                                <thead>
                                    <tr><th>Contestant No.</th><th>Name</th><th>Score (1-100)</th></tr>
                                </thead>
                                <tbody>
                                    <?php while($c = $contestants_res->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($c['id']) ?></td>
                                        <td><?= htmlspecialchars($c['name']) ?></td>
                                        <td><input type="number" min="1" max="100" step="0.01" name="scores[<?= $c['id'] ?>]" value="<?= htmlspecialchars($scores[$c['id']] ?? '') ?>" required></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                            <button type="submit" class="btn" style="margin-top: 15px;">Submit Scores</button>
                        </form>
                    <?php else: ?>
                        <p>Please select a criterion to begin scoring, or add contestants/criteria in the admin panel.</p>
                    <?php endif; ?>
                </div>
            </main>
            <aside>
                <div class="kpi-container">
                    <div class="kpi-card">
                        <h3><?= $contestant_count ?></h3>
                        <p>Contestants</p>
                    </div>
                    <div class="kpi-card">
                        <h3><?= $criteria_count ?></h3>
                        <p>Criteria</p>
                    </div>
                </div>
                <div class="mini-leaderboard">
                    <h3>Live Leaderboard</h3>
                    <?php if ($leaderboard_res && $leaderboard_res->num_rows > 0): ?>
                    <table>
                        <?php for ($i = 0; $i < 3 && $row = $leaderboard_res->fetch_assoc(); $i++): ?>
                        <tr><td>#<?= $i + 1 ?> <?= htmlspecialchars($row['name']) ?></td><td><strong><?= number_format($row['final_score'], 2) ?></strong></td></tr>
                        <?php endfor; ?>
                    </table>
                    <?php else: ?>
                    <p class="small">No scores submitted yet.</p>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </div>
</body>
</html>