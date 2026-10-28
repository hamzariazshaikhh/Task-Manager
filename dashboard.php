<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';

$error = '';
$success = '';

// Handle New Project Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_project'])) {
    $title       = trim($_POST['title']);
    $description = trim($_POST['description']);

    if (empty($title)) {
        $error = "Project title is required.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO projects (user_id, title, description) VALUES (?, ?, ?)");
        if ($stmt->execute([$_SESSION['user_id'], $title, $description])) {
            $success = "Project created successfully!";
        } else {
            $error = "Failed to create project.";
        }
    }
}

// Fetch user's projects
$stmt = $pdo->prepare("SELECT * FROM projects WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$projects = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Task Manager</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <h1>Task Manager Board</h1>
        <div class="user-info">
            <span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
            <button type="button" id="theme-toggle" class="theme-toggle" aria-label="Toggle dark theme">🌙</button>

            <a href="logout.php" class="btn logout-btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="dashboard-header">
            <h2>Your Projects</h2>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Create Project Form -->
        <div class="auth-container" style="max-width: 100%; margin: 0 0 32px 0; padding: 24px;">
            <h3>Create New Project</h3>
            <form action="dashboard.php" method="POST" style="display: flex; gap: 16px; align-items: flex-end; margin-top: 12px;">
                <div class="form-group" style="flex: 1; margin-bottom: 0;">
                    <label>Project Title</label>
                    <input type="text" name="title" required placeholder="e.g. Website Redesign">
                </div>
                <div class="form-group" style="flex: 2; margin-bottom: 0;">
                    <label>Description</label>
                    <input type="text" name="description" placeholder="Brief project summary...">
                </div>
                <button type="submit" name="create_project" class="btn" style="width: auto; padding: 10px 20px;">Create</button>
            </form>
        </div>

        <!-- Project Grid -->
        <div class="project-grid">
            <?php if (count($projects) > 0): ?>
                <?php foreach ($projects as $project): ?>
                    <div class="project-card">
                        <div>
                            <h3><?php echo htmlspecialchars($project['title']); ?></h3>
                            <p><?php echo htmlspecialchars($project['description'] ?: 'No description provided.'); ?></p>
                        </div>
                        <div class="card-actions">
                            <a href="project.php?id=<?php echo $project['id']; ?>" class="btn btn-sm">View Board</a>
                            <a href="project.php?id=<?php echo (int) $project['id']; ?>&edit=1" class="btn btn-sm">Edit</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No projects found. Create your first project above!</p>
            <?php endif; ?>
        </div>
    </div>
     <script src="assets/js/main.js"></script>
</body>
</html>