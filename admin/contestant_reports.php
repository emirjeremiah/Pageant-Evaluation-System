<?php
session_start();
require_once '../database/db.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get the default competition ID (most recent) if none is selected
$default_competition_id = 0;
$default_comp_result = $conn->query("SELECT id FROM competitions ORDER BY created_at DESC LIMIT 1");
if ($default_comp_result && $default_comp_result->num_rows > 0) {
    $default_competition_id = $default_comp_result->fetch_assoc()['id'];
}

// Get selected competition ID, or use the dynamic default
$competition_id = (int)($_GET['competition_id'] ?? $default_competition_id);

// Fetch all competitions for the dropdown selector
$all_competitions_result = $conn->query("SELECT id, name FROM competitions ORDER BY name ASC");

// Fetch detailed scoring data for each contestant
$stmt = $conn->prepare(
    "SELECT 
        con.id AS contestant_id,
        con.name AS contestant_name,
        con.age,
        j.name AS judge_name,
        SUM(s.score * (cri.percentage / 100)) AS final_score_from_judge
     FROM contestants con
     LEFT JOIN scores s ON con.id = s.contestant_id
     LEFT JOIN judges j ON s.judge_id = j.id
     LEFT JOIN criteria cri ON s.criteria_id = cri.id
     WHERE con.competition_id = ?
     GROUP BY con.id, j.id
     ORDER BY con.name, j.name"
);
$stmt->bind_param("i", $competition_id);
$stmt->execute();
$scores_by_contestant_result = $stmt->get_result();

$contestants_data = [];
while ($row = $scores_by_contestant_result->fetch_assoc()) {
    $contestants_data[$row['contestant_id']]['details']['name'] = $row['contestant_name'];
    $contestants_data[$row['contestant_id']]['details']['age'] = $row['age'];
    if ($row['judge_name']) { // Only add score if a judge has scored them
        $contestants_data[$row['contestant_id']]['scores'][] = $row;
    }
}

// Get the name of the current competition for the report title
$competition_name_stmt = $conn->prepare("SELECT name FROM competitions WHERE id = ?");
$competition_name_stmt->bind_param("i", $competition_id);
$competition_name_stmt->execute();
$competition_name_result = $competition_name_stmt->get_result();
$competition_name = $competition_name_result->fetch_assoc()['name'] ?? 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Contestant Reports - Pageant Evaluation System</title>
  <link rel="stylesheet" href="../style.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
  <div class="topbar">
    <div class="brand">Contestant Report</div>
    <div class="links">
      <a href="admin_dashboard.php">Dashboard</a>
      <a href="reports.php">Reports</a>
      <a href="../logout.php">Logout</a>
    </div>
  </div>

  <div class="wrapper">
    <div class="header-row">
        <h2>Contestants for "<?= htmlspecialchars($competition_name) ?>"</h2>
        <form method="GET" class="competition-selector">
            <label for="competition_id">Select Competition:</label>
            <select name="competition_id" id="competition_id" class="input" onchange="this.form.submit();">
                <?php while($comp = $all_competitions_result->fetch_assoc()): ?>
                    <option value="<?= $comp['id'] ?>" <?= $comp['id'] == $competition_id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($comp['name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </form>
    </div>

    <div class="report-actions">
      <button class="btn" onclick="window.print()">Print Report</button>
      <button class="btn" onclick="downloadPDF()">Download PDF</button>
    </div>

    <table class="report-table" id="report-table">
      <thead>
        <tr><th>Contestant Name</th><th>Overall Score</th><th>Score Breakdown by Judge</th></tr>
      </thead>
      <tbody>
        <?php if (empty($contestants_data)): ?>
            <tr><td colspan="3" style="text-align: center;">No contestants found for this competition.</td></tr>
        <?php else: ?>
            <?php foreach ($contestants_data as $contestant): ?>
                <?php
                    $total_score = 0;
                    $judge_count = 0;
                    if (isset($contestant['scores'])) {
                        foreach ($contestant['scores'] as $score_info) {
                            $total_score += $score_info['final_score_from_judge'];
                        }
                        $judge_count = count($contestant['scores']);
                    }
                    $overall_avg = ($judge_count > 0) ? $total_score / $judge_count : 0;
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($contestant['details']['name']) ?></strong><br><small>Age: <?= htmlspecialchars($contestant['details']['age']) ?></small></td>
                    <td><strong><?= number_format($overall_avg, 2) ?></strong></td>
                    <td>
                        <?php if (isset($contestant['scores'])): ?>
                            <?php foreach ($contestant['scores'] as $score_info): ?>
                                <div style="font-size: 13px;"><?= htmlspecialchars($score_info['judge_name']) ?>: <strong><?= number_format($score_info['final_score_from_judge'], 2) ?></strong></div>
                            <?php endforeach; ?>
                        <?php else: echo '<small>Not yet scored.</small>'; endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <script>
    function downloadPDF() {
      const { jsPDF } = window.jspdf;
      const doc = new jsPDF();
      const competitionName = "<?= htmlspecialchars($competition_name) ?>";
      doc.text(`Contestant Report for: ${competitionName}`, 14, 20);
      doc.autoTable({ html: '#report-table', startY: 30 });
      doc.save(`Contestant_Report_${competitionName.replace(/ /g, '_')}.pdf`);
    }
  </script>
</body>
</html>