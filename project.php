<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';

// Get and validate Project ID from URL
$project_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$project_id) {
    header("Location: dashboard.php");
    exit();
}

// Verify that the project belongs to the logged-in user (Security Check)
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
$stmt->execute([$project_id, $_SESSION['user_id']]);
$project = $stmt->fetch();

if (!$project) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';


$success_messages = [
    'task_added' => 'Task added successfully!',
    'comment_added' => 'Comment added successfully!',
    'project_updated' => 'Project details updated successfully!'
];

$success_key = $_GET['success'] ?? '';

if (array_key_exists($success_key, $success_messages)) {
    $success = $success_messages[$success_key];
}

function addAuditLog(PDO $pdo, int $project_id, int $user_id, string $description): void
{
    $auditStmt = $pdo->prepare("
        INSERT INTO audit_logs
            (project_id, user_id, action_description)
        VALUES (?, ?, ?)
    ");

    $auditStmt->execute([
        $project_id,
        $user_id,
        $description
    ]);
}


// Handle project details update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project'])) {
    $project_title = trim($_POST['project_title'] ?? '');
    $project_description = trim($_POST['project_description'] ?? '');

    if ($project_title === '') {
        $error = 'Project title is required.';
    } else {
        $updateStmt = $pdo->prepare("
            UPDATE projects
            SET title = ?, description = ?
            WHERE id = ? AND user_id = ?
        ");

        $updateStmt->execute([
            $project_title,
            $project_description,
            $project_id,
            $_SESSION['user_id']
        ]);

        if ($updateStmt->rowCount() > 0) {
            addAuditLog(
                $pdo,
                $project_id,
                (int) $_SESSION['user_id'],
                'updated project details'
            );

            header('Location: project.php?id=' . $project_id . '&success=project_updated');
            exit();
        }

        $error = 'Project details could not be updated.';
    }
}

// Handle Task Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

    if ($title === '') {
        $error = "Task title is required.";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO tasks
                (project_id, title, description, priority, due_date)
            VALUES (?, ?, ?, ?, ?)
        ");

    }
if ($stmt->execute([
    $project_id,
    $title,
    $description,
    $priority,
    $due_date
])) {
    addAuditLog(
        $pdo,
        $project_id,
        (int) $_SESSION['user_id'],
        'created task "' . $title . '"'
    );

    header('Location: project.php?id=' . $project_id . '&success=task_added');
    exit();
}
}



// Handle Comment Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $task_id = filter_var($_POST['task_id'] ?? null, FILTER_VALIDATE_INT);
    $comment_text = trim($_POST['comment_text'] ?? '');

    if (!$task_id) {
        $error = 'Invalid task.';
    } elseif ($comment_text === '') {
        $error = 'Comment cannot be empty.';
    } else {
        $taskStmt = $pdo->prepare("
            SELECT t.title
            FROM tasks t
            INNER JOIN projects p ON t.project_id = p.id
            WHERE t.id = ?
              AND t.project_id = ?
              AND p.user_id = ?
        ");

        $taskStmt->execute([
            $task_id,
            $project_id,
            $_SESSION['user_id']
        ]);

        $task = $taskStmt->fetch();

        if (!$task) {
            $error = 'Task not found.';
        } else {
            $commentStmt = $pdo->prepare("
                INSERT INTO task_comments
                    (task_id, user_id, comment_text)
                VALUES (?, ?, ?)
            ");

            $commentStmt->execute([
                $task_id,
                $_SESSION['user_id'],
                $comment_text
            ]);

            addAuditLog(
                $pdo,
                $project_id,
                (int) $_SESSION['user_id'],
                'commented on task "' . $task['title'] . '"'
            );

            header('Location: project.php?id=' . $project_id . '&success=comment_added');
            exit();
        }
    }
}



// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $task_id = filter_var($_POST['task_id'] ?? null, FILTER_VALIDATE_INT);
    $new_status = $_POST['status'] ?? '';

    $allowed_statuses = [
        'todo' => 'To Do',
        'in_progress' => 'In Progress',
        'done' => 'Done'
    ];

    if (
        $task_id &&
        array_key_exists($new_status, $allowed_statuses)
    ) {
        $taskStmt = $pdo->prepare("
            SELECT t.status, t.title
            FROM tasks t
            INNER JOIN projects p ON t.project_id = p.id
            WHERE t.id = ?
              AND t.project_id = ?
              AND p.user_id = ?
        ");

        $taskStmt->execute([
            $task_id,
            $project_id,
            $_SESSION['user_id']
        ]);

        $task = $taskStmt->fetch();

        if ($task && $task['status'] !== $new_status) {
            $old_status_labels = [
                'todo' => 'To Do',
                'in_progress' => 'In Progress',
                'done' => 'Done'
            ];

            $updateStmt = $pdo->prepare("
                UPDATE tasks
                SET status = ?
                WHERE id = ?
                  AND project_id = ?
            ");

            $updateStmt->execute([
                $new_status,
                $task_id,
                $project_id
            ]);

            addAuditLog(
                $pdo,
                $project_id,
                (int) $_SESSION['user_id'],
                'changed task "' . $task['title'] .
                '" status from ' . $old_status_labels[$task['status']] .
                ' to ' . $allowed_statuses[$new_status]
            );
        }
    }

    header('Location: project.php?id=' . $project_id);
    exit();
}


// Handle Task Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_task'])) {
    $task_id = filter_var($_POST['task_id'] ?? null, FILTER_VALIDATE_INT);

    if ($task_id) {
        $taskStmt = $pdo->prepare("
            SELECT t.title
            FROM tasks t
            INNER JOIN projects p ON t.project_id = p.id
            WHERE t.id = ?
              AND t.project_id = ?
              AND p.user_id = ?
        ");

        $taskStmt->execute([
            $task_id,
            $project_id,
            $_SESSION['user_id']
        ]);

        $task = $taskStmt->fetch();

        if ($task) {
            $delStmt = $pdo->prepare("
                DELETE FROM tasks
                WHERE id = ?
                  AND project_id = ?
            ");

            $delStmt->execute([
                $task_id,
                $project_id
            ]);

            addAuditLog(
                $pdo,
                $project_id,
                (int) $_SESSION['user_id'],
                'deleted task "' . $task['title'] . '"'
            );
        }
    }

    header('Location: project.php?id=' . $project_id);
    exit();
}

// Fetch all tasks for this project

// Fetch tasks using optional filters
$search = trim($_GET['search'] ?? '');
$filter_priority = $_GET['priority'] ?? '';
$filter_status = $_GET['status'] ?? '';

$allowed_priorities = ['low', 'medium', 'high'];
$allowed_statuses = ['todo', 'in_progress', 'done'];

if (!in_array($filter_priority, $allowed_priorities, true)) {
    $filter_priority = '';
}

if (!in_array($filter_status, $allowed_statuses, true)) {
    $filter_status = '';
}

$query = "
    SELECT
        tasks.*,
        COALESCE(comment_counts.comment_count, 0) AS comment_count
    FROM tasks
    LEFT JOIN (
        SELECT task_id, COUNT(*) AS comment_count
        FROM task_comments
        GROUP BY task_id
    ) AS comment_counts
        ON comment_counts.task_id = tasks.id
    WHERE tasks.project_id = ?
";
$params = [$project_id];

if ($search !== '') {
    $query .= " AND (tasks.title LIKE ? OR tasks.description LIKE ?)";
    $search_pattern = '%' . $search . '%';
    $params[] = $search_pattern;
    $params[] = $search_pattern;
}

if ($filter_priority !== '') {
    $query .= " AND tasks.priority = ?";
    $params[] = $filter_priority;
}

if ($filter_status !== '') {
    $query .= " AND tasks.status = ?";
    $params[] = $filter_status;
}

$query .= " ORDER BY due_date ASC, created_at DESC";

$taskStmt = $pdo->prepare($query);
$taskStmt->execute($params);
$tasks = $taskStmt->fetchAll();
// Group tasks by status
$kanban = [
    'todo' => [],
    'in_progress' => [],
    'done' => []
];
foreach ($tasks as $task) {
    if (array_key_exists($task['status'], $kanban)) {
        $kanban[$task['status']][] = $task;
    }
}

// Fetch all comments for the tasks shown on this page
$commentsByTask = [];

if (!empty($tasks)) {
    $taskIds = array_column($tasks, 'id');
    $placeholders = implode(',', array_fill(0, count($taskIds), '?'));

    $commentsStmt = $pdo->prepare("
        SELECT
            task_comments.id,
            task_comments.task_id,
            task_comments.comment_text,
            task_comments.created_at,
            users.username
        FROM task_comments
        INNER JOIN users
            ON users.id = task_comments.user_id
        WHERE task_comments.task_id IN ($placeholders)
        ORDER BY task_comments.created_at ASC
    ");

    $commentsStmt->execute($taskIds);

    foreach ($commentsStmt->fetchAll() as $comment) {
        $commentsByTask[$comment['task_id']][] = $comment;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($project['title']); ?> - Task Board</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <h1>Task Manager Board</h1>
        <div class="user-info">
            <a href="dashboard.php" class="btn btn-sm" style="width: auto;">&larr; Back to Dashboard</a>
            <span><strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
            <button type="button" id="theme-toggle" class="theme-toggle" aria-label="Toggle dark theme">🌙</button>
            <a href="logout.php" class="btn logout-btn">Logout</a>
        </div>
    </nav>
<div class="container">
    <div class="project-meta">
        <div>
            <?php if (isset($_GET['edit'])): ?>
                <form method="POST" action="project.php?id=<?php echo (int) $project_id; ?>">
                    <div class="form-group">
                        <label for="project-title">Project Title</label>
                        <input
                            type="text"
                            id="project-title"
                            name="project_title"
                            value="<?php echo htmlspecialchars($project['title']); ?>"
                            maxlength="100"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="project-description">Project Description</label>
                        <textarea
                            id="project-description"
                            name="project_description"
                            rows="4"
                        ><?php echo htmlspecialchars($project['description'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" name="update_project" class="btn">
                        Save Changes
                    </button>

                    <a
                        href="project.php?id=<?php echo (int) $project_id; ?>"
                        class="btn btn-sm"
                    >
                        Cancel
                    </a>
                </form>
            <?php else: ?>
                <h2><?php echo htmlspecialchars($project['title']); ?></h2>

                <p>
                    <?php echo htmlspecialchars($project['description'] ?: 'No project description.'); ?>
                </p>

                <a
                    href="project.php?id=<?php echo (int) $project_id; ?>&edit=1"
                    class="btn btn-sm"
                >
                    Edit Project
                </a>
            <?php endif; ?>
        </div>
    </div>
    
  
      <?php if (!empty($error)): ?>
    <div id="error-message" class="alert error">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div id="success-message" class="alert success">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

        <!-- Add Task Form -->
        <div class="auth-container" style="max-width: 100%; margin: 0 0 32px 0; padding: 24px;">
            <h3>Add New Task</h3>
            <form action="project.php?id=<?php echo $project_id; ?>" method="POST" style="display: flex; gap: 12px; align-items: flex-end; margin-top: 12px; flex-wrap: wrap;">
                <div class="form-group" style="flex: 2; min-width: 200px; margin-bottom: 0;">
                    <label>Task Title</label>
                    <input type="text" name="title" required placeholder="e.g. Design landing page hero">
                </div>
                <div class="form-group" style="flex: 3; min-width: 250px; margin-bottom: 0;">
                    <label>Description</label>
                    <input type="text" name="description" placeholder="Task details...">
                </div>
                <div class="form-group" style="flex: 1; min-width: 120px; margin-bottom: 0;">
                    <label>Priority</label>
                    <select name="priority">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 1; min-width: 130px; margin-bottom: 0;">
                    <label>Due Date</label>
                    <input type="date" name="due_date">
                </div>
                <button type="submit" name="create_task" class="btn" style="width: auto; padding: 10px 20px;">Add Task</button>
            </form>
        </div>

        
         <!-- Task Filters -->

        <div class="auth-container" style="max-width: 100%; margin: 0 0 32px 0; padding: 24px;">
            <h3>Filter Tasks</h3>

    <form action="project.php" method="GET" style="display: flex; gap: 12px; align-items: flex-end; margin-top: 12px; flex-wrap: wrap;">
        <input type="hidden" name="id" value="<?php echo $project_id; ?>">

        <div class="form-group" style="flex: 2; min-width: 220px; margin-bottom: 0;">
            <label for="search">Search</label>
            <input
                type="search"
                id="search"
                name="search"
                placeholder="Search title or description..."
                value="<?php echo htmlspecialchars($search); ?>"
            >
        </div>

        <div class="form-group" style="flex: 1; min-width: 140px; margin-bottom: 0;">
            <label for="filter-priority">Priority</label>
            <select id="filter-priority" name="priority">
                <option value="">All priorities</option>
                <option value="low" <?php echo $filter_priority === 'low' ? 'selected' : ''; ?>>Low</option>
                <option value="medium" <?php echo $filter_priority === 'medium' ? 'selected' : ''; ?>>Medium</option>
                <option value="high" <?php echo $filter_priority === 'high' ? 'selected' : ''; ?>>High</option>
            </select>
        </div>

        <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
            <label for="filter-status">Status</label>
            <select id="filter-status" name="status">
                <option value="">All statuses</option>
                <option value="todo" <?php echo $filter_status === 'todo' ? 'selected' : ''; ?>>To Do</option>
                <option value="in_progress" <?php echo $filter_status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                <option value="done" <?php echo $filter_status === 'done' ? 'selected' : ''; ?>>Done</option>
            </select>
        </div>

        <button type="submit" class="btn" style="width: auto; padding: 10px 20px;">
            Filter
        </button>

        <a href="project.php?id=<?php echo $project_id; ?>" class="btn btn-sm">
            Clear
        </a>
    </form>
</div>

        <!-- Kanban Board View -->
        <div class="kanban-board">
            <!-- To Do Column -->
            <div class="kanban-column">
                <h3>To Do (<?php echo count($kanban['todo']); ?>)</h3>
                <?php foreach ($kanban['todo'] as $task): ?>
    <?php
    $is_overdue = (
        !empty($task['due_date']) &&
        $task['due_date'] < date('Y-m-d') &&
        $task['status'] !== 'done'
    );
    ?>
    <div class="task-card<?php echo $is_overdue ? ' overdue' : ''; ?>">
                
                        <h4><?php echo htmlspecialchars($task['title']); ?></h4>
                        <?php if ($task['description']): ?>
                            <p><?php echo htmlspecialchars($task['description']); ?></p>
                        <?php endif; ?>

                       <?php if (!empty($task['due_date'])): ?>
    <div class="task-due-date">
        Due: <?php echo htmlspecialchars($task['due_date']); ?>
    </div>
<?php endif; ?>

<div class="task-comments">
    <strong>
        Comments (<?php echo count($commentsByTask[$task['id']] ?? []); ?>)
    </strong>

    <?php foreach ($commentsByTask[$task['id']] ?? [] as $comment): ?>
        <div class="comment">
            <div class="comment-author">
                <?php echo htmlspecialchars($comment['username']); ?>

                <small>
                    <?php echo htmlspecialchars($comment['created_at']); ?>
                </small>
            </div>

            <p>
                <?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?>
            </p>
        </div>
    <?php endforeach; ?>

    <form method="POST" class="comment-form">
        <input
            type="hidden"
            name="task_id"
            value="<?php echo (int) $task['id']; ?>"
        >

        <textarea
            name="comment_text"
            rows="2"
            maxlength="2000"
            required
            placeholder="Write a comment..."
        ></textarea>

        <button type="submit" name="add_comment" class="btn btn-sm">
            Add Comment
        </button>
    </form>
</div>


                        <div class="task-footer">
                            <span class="badge badge-<?php echo $task['priority']; ?>"><?php echo $task['priority']; ?></span>
                            <form method="POST" style="display:flex; gap:4px; align-items:center;">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <select name="status" onchange="this.form.submit()" style="font-size:11px; padding:2px;">
                                    <option value="todo" selected>To Do</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="done">Done</option>
                                </select>
                                <input type="hidden" name="update_status" value="1">
                            </form>
                            <form method="POST" onsubmit="return confirm('Delete this task?');">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <button type="submit" name="delete_task" style="background:none; border:none; color:#de350b; cursor:pointer; font-size:12px;">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- In Progress Column -->
            <div class="kanban-column">
                <h3>In Progress (<?php echo count($kanban['in_progress']); ?>)</h3>
               <?php foreach ($kanban['in_progress'] as $task): ?>
    <?php
    $is_overdue = (
        !empty($task['due_date']) &&
        $task['due_date'] < date('Y-m-d') &&
        $task['status'] !== 'done'
    );
    ?>
    <div class="task-card<?php echo $is_overdue ? ' overdue' : ''; ?>">
                        <h4><?php echo htmlspecialchars($task['title']); ?></h4>
                        <?php if ($task['description']): ?>
                            <p><?php echo htmlspecialchars($task['description']); ?></p>
                        <?php endif; ?>
                        <div class="task-footer">
                            <span class="badge badge-<?php echo $task['priority']; ?>"><?php echo $task['priority']; ?></span>
                            <form method="POST" style="display:flex; gap:4px; align-items:center;">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <select name="status" onchange="this.form.submit()" style="font-size:11px; padding:2px;">
                                    <option value="todo">To Do</option>
                                    <option value="in_progress" selected>In Progress</option>
                                    <option value="done">Done</option>
                                </select>
                                <input type="hidden" name="update_status" value="1">
                            </form>
                            <form method="POST" onsubmit="return confirm('Delete this task?');">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <button type="submit" name="delete_task" style="background:none; border:none; color:#de350b; cursor:pointer; font-size:12px;">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Done Column -->
            <div class="kanban-column">
                <h3>Done (<?php echo count($kanban['done']); ?>)</h3>
                <?php foreach ($kanban['done'] as $task): ?>
    <?php
    $is_overdue = (
        !empty($task['due_date']) &&
        $task['due_date'] < date('Y-m-d') &&
        $task['status'] !== 'done'
    );
    ?>
    <div class="task-card<?php echo $is_overdue ? ' overdue' : ''; ?>">
                        <h4><?php echo htmlspecialchars($task['title']); ?></h4>
                        <?php if ($task['description']): ?>
                            <p><?php echo htmlspecialchars($task['description']); ?></p>
                        <?php endif; ?>
                        <div class="task-footer">
                            <span class="badge badge-<?php echo $task['priority']; ?>"><?php echo $task['priority']; ?></span>
                            <form method="POST" style="display:flex; gap:4px; align-items:center;">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <select name="status" onchange="this.form.submit()" style="font-size:11px; padding:2px;">
                                    <option value="todo">To Do</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="done" selected>Done</option>
                                </select>
                                <input type="hidden" name="update_status" value="1">
                            </form>
                            <form method="POST" onsubmit="return confirm('Delete this task?');">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <button type="submit" name="delete_task" style="background:none; border:none; color:#de350b; cursor:pointer; font-size:12px;">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <script src="assets/js/main.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const messages = document.querySelectorAll('#success-message, #error-message');

    messages.forEach((message) => {
        setTimeout(() => {
            message.style.opacity = '0';

            setTimeout(() => {
                message.remove();
            }, 300);
        }, 4000);
    });

    const url = new URL(window.location.href);

    if (url.searchParams.get('success') === 'task_added') {
        url.searchParams.delete('success');

        const cleanUrl = url.pathname + url.search;
        window.history.replaceState({}, document.title, cleanUrl);
    }
});
</script>
</body>

</body>
</html>