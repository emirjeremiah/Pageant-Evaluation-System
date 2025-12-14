<?php
session_start();
require_once __DIR__ . '/../database/db.php';

// If judge is not authenticated, redirect to main login page.
if (!isset($_SESSION['judge_auth_id'])) {
    header('Location: ../login.php');
    exit;
}

$judge_id = $_SESSION['judge_auth_id'];
$error_message = '';

// Fetch competitions this specific judge is assigned to
$stmt = $conn->prepare(
    "SELECT c.id, c.name 
     FROM competitions c
     JOIN competition_judges cj ON c.id = cj.competition_id
     WHERE cj.judge_id = ? 
     ORDER BY c.name ASC"
);
$stmt->bind_param("i", $judge_id);
$stmt->execute();
$competitions = $stmt->get_result();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $competition_id = (int)($_POST['competition_id'] ?? 0);

    if ($competition_id > 0) {
        // Set the final session variables for the judge dashboard
        $_SESSION['judge_id'] = $judge_id;
        $_SESSION['judge_username'] = $_SESSION['judge_auth_username'];
        $_SESSION['competition_id'] = $competition_id;

        // Clean up temporary auth session variables
        unset($_SESSION['judge_auth_id']);
        unset($_SESSION['judge_auth_username']);

        header("Location: judge_dashboard.php");
        exit();
    } else {
        $error_message = 'Please select a valid competition.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Select Competition - Pageant Evaluation System</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
  <div class="login-container">
    <div class="login-box">
      <h2>Select Competition</h2>
      <p>Welcome, <?= htmlspecialchars($_SESSION['judge_auth_username']) ?>! Please choose the competition you will be judging.</p>
      <?php if ($error_message): ?><p class="error"><?= htmlspecialchars($error_message) ?></p><?php endif; ?>
      <form action="select_competition.php" method="POST">
        <label for="competition_id">Competition</label>
        <select id="competition_id" name="competition_id" required>
            <option value="">-- Select a Competition --</option>
            <?php while($comp = $competitions->fetch_assoc()): ?>
                <option value="<?= $comp['id'] ?>"><?= htmlspecialchars($comp['name']) ?></option>
            <?php endwhile; ?>
        </select>
        <button type="submit" class="btn">Proceed to Judging</button>
      </form>
    </div>
  </div>
</body>
</html>