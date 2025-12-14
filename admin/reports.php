<?php
session_start();
require_once '../database/db.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Reports - Pageant Evaluation System</title>
  <link rel="stylesheet" href="../style.css">
  <style>
    .report-link-card {
      display: block;
      background: white;
      padding: 25px;
      border-radius: var(--radius);
      box-shadow: var(--card-shadow);
      text-decoration: none;
      color: var(--text);
      margin-bottom: 20px;
      transition: all 0.2s ease;
    }
    .report-link-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    }
    .report-link-card h3 { margin: 0 0 5px 0; color: var(--gold-dark); }
    .report-link-card p { margin: 0; color: var(--muted); }
  </style>
</head>
<body>
  <div class="topbar">
    <div class="brand">Admin Reports</div>
    <div class="links">
      <a href="admin_dashboard.php">Dashboard</a>
      <a href="../logout.php">Logout</a>
    </div>
  </div>

  <div class="wrapper">
    <h2>Select a Report to View</h2>
    <a href="judge_reports.php" class="report-link-card">
      <h3>Judge Report</h3>
      <p>View a list of all judges assigned to a specific competition.</p>
    </a>
    <a href="contestant_reports.php" class="report-link-card">
      <h3>Contestant Report</h3>
      <p>View a list of all contestants participating in a specific competition.</p>
    </a>
  </div>
</body>
</html>