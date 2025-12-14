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

// Fetch detailed scoring data grouped by judge for the selected competition
$stmt = $conn->prepare(
    "SELECT 
        j.name AS judge_name, 
        con.name AS contestant_name,
        SUM(s.score * (cri.percentage / 100)) AS final_score_from_judge
     FROM scores s
     JOIN judges j ON s.judge_id = j.id
     JOIN contestants con ON s.contestant_id = con.id
     JOIN criteria cri ON s.criteria_id = cri.id
     WHERE s.competition_id = ?
     GROUP BY j.id, con.id
     ORDER BY j.name, con.name"
);
$stmt->bind_param("i", $competition_id);
$stmt->execute();
$scores_by_judge_result = $stmt->get_result();

$judges_data = [];
while ($row = $scores_by_judge_result->fetch_assoc()) {
    $judges_data[$row['judge_name']][] = $row;
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
  <title>Judge Reports - Pageant Evaluation System</title>
  <link rel="stylesheet" href="../style.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
  <div class="topbar">
    <div class="brand">Judge Report</div>
    <div class="links">
      <a href="admin_dashboard.php">Dashboard</a>
      <a href="reports.php">Reports</a>
      <a href="../logout.php">Logout</a>
    </div>
  </div>

  <div class="wrapper">
    <div class="header-row">
        <h2>Judges for "<?= htmlspecialchars($competition_name) ?>"</h2>
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
        <tr><th>Judge Name</th><th>Contestant Scored</th><th>Final Score Given</th></tr>
      </thead>
      <tbody>
        <?php if (empty($judges_data)): ?>
            <tr><td colspan="3" style="text-align: center;">No scores have been submitted for this competition yet.</td></tr>
        <?php else: ?>
            <?php foreach ($judges_data as $judge_name => $scores): ?>
                <?php foreach ($scores as $i => $score_info): ?>
                    <tr>
                        <?php if ($i === 0): /* Show judge name only on the first row */ ?>
                            <td rowspan="<?= count($scores) ?>"><?= htmlspecialchars($judge_name) ?></td>
                        <?php endif; ?>
                        <td><?= htmlspecialchars($score_info['contestant_name']) ?></td>
                        <td><strong><?= number_format($score_info['final_score_from_judge'], 2) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
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
      doc.text(`Judge Report for: ${competitionName}`, 14, 20);
      doc.autoTable({ html: '#report-table', startY: 30 });
      doc.save(`Judge_Report_${competitionName.replace(/ /g, '_')}.pdf`);
    }
  </script>
</body>
</html>