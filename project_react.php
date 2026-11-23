<?php
require_once 'config/kuis_db.php';
require_once 'middleware/auth_check.php';
require_once 'includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['ok' => false]));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false]));
}
csrf_verify();

$projectId = (int) ($_POST['project_id'] ?? 0);
$reaction  = $_POST['reaction'] ?? '';
$userId    = (int) $_SESSION['user_id'];

if ($projectId <= 0 || !is_string($reaction) || !isset(PROJECT_REACTIONS[$reaction])) {
    http_response_code(400);
    exit(json_encode(['ok' => false]));
}

$cek = $pdo->prepare("SELECT id FROM projects WHERE id = :id");
$cek->execute([':id' => $projectId]);
if (!$cek->fetch()) {
    http_response_code(404);
    exit(json_encode(['ok' => false]));
}

$cur = $pdo->prepare("SELECT reaction FROM project_reactions WHERE project_id = :p AND user_id = :u");
$cur->execute([':p' => $projectId, ':u' => $userId]);
$current = $cur->fetchColumn();

if ($current === $reaction) {            // klik reaksi yang sama => batal
    $pdo->prepare("DELETE FROM project_reactions WHERE project_id = :p AND user_id = :u")
        ->execute([':p' => $projectId, ':u' => $userId]);
    $mine = null;
} elseif ($current !== false) {          // ganti reaksi
    $pdo->prepare("UPDATE project_reactions SET reaction = :r WHERE project_id = :p AND user_id = :u")
        ->execute([':r' => $reaction, ':p' => $projectId, ':u' => $userId]);
    $mine = $reaction;
} else {                                 // reaksi baru
    $pdo->prepare("INSERT INTO project_reactions (project_id, user_id, reaction) VALUES (:p, :u, :r)")
        ->execute([':p' => $projectId, ':u' => $userId, ':r' => $reaction]);
    $mine = $reaction;
}

$cnt = $pdo->prepare("SELECT reaction, COUNT(*) AS total FROM project_reactions
                      WHERE project_id = :p GROUP BY reaction");
$cnt->execute([':p' => $projectId]);
$counts = [];
foreach ($cnt->fetchAll() as $row) {
    $counts[$row['reaction']] = (int) $row['total'];
}

echo json_encode(['ok' => true, 'counts' => $counts, 'mine' => $mine]);
