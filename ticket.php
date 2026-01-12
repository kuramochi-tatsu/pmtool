<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib.php';

$pdo = db();

$assignees = $pdo->query("SELECT id, name FROM pm_assignees WHERE is_active=true ORDER BY sort_order, id")->fetchAll();
$statuses  = $pdo->query("SELECT id, name FROM pm_statuses  WHERE is_active=true ORDER BY sort_order, id")->fetchAll();
$categories = $pdo->query("SELECT id, name FROM pm_categories WHERE is_active=true ORDER BY sort_order, id")->fetchAll();

$id = q('id', '');
$isEdit = ($id !== '' && ctype_digit((string)$id));
$ticket = null;
$comments = [];

function fetch_ticket(PDO $pdo, int $id) {
  $sql = "
    SELECT t.*,
      a.name AS assignee_name,
      s.name AS status_name,
      c.name AS category_name
    FROM pm_tickets t
    LEFT JOIN pm_assignees a ON a.id=t.assignee_id
    LEFT JOIN pm_statuses  s ON s.id=t.status_id
    LEFT JOIN pm_categories c ON c.id=t.category_id
    WHERE t.id = :id
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':id'=>$id]);
  return $st->fetch();
}

function fetch_comments(PDO $pdo, int $ticketId) {
  $st = $pdo->prepare("SELECT * FROM pm_ticket_comments WHERE ticket_id=:tid ORDER BY created_at ASC, id ASC");
  $st->execute([':tid'=>$ticketId]);
  return $st->fetchAll();
}

/**
 * URLをリンク化して、改行も反映する
 */
function linkify_nl(string $text): string {
  $pattern = '~(https?://[^\s<>"\']+)~i';
  $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);

  $out = '';
  for ($i = 0; $i < count($parts); $i++) {
    if ($i % 2 === 1) {
      $url = $parts[$i];
      $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
      $out .= '<a href="'.$safeUrl.'" target="_blank" rel="noopener noreferrer">'.$safeUrl.'</a>';
    } else {
      $out .= htmlspecialchars($parts[$i], ENT_QUOTES, 'UTF-8');
    }
  }
  return nl2br($out);
}

if ($isEdit) {
  $ticket = fetch_ticket($pdo, (int)$id);
  if (!$ticket) { http_response_code(404); echo "Not Found"; exit; }
  $comments = fetch_comments($pdo, (int)$id);
}

// POST処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();

  $mode = p('mode', '');
  if ($mode === 'create') {
    $title = trim((string)p('title',''));
    $description = trim((string)p('description',''));
    $assigneeId = p('assignee_id','');
    $statusId   = p('status_id','');
    $categoryId = p('category_id','');
    $dueDate    = p('due_date','');

    if ($title === '') {
      $_SESSION['flash_error'] = 'タイトルは必須です';
      redirect('ticket.php');
    }

    $sql = "
      INSERT INTO pm_tickets(title, description, assignee_id, status_id, category_id, due_date, created_at, updated_at)
      VALUES(:title, :description, :assignee_id, :status_id, :category_id, :due_date, NOW(), NOW())
      RETURNING id
    ";
    $st = $pdo->prepare($sql);
    $st->execute([
      ':title' => $title,
      ':description' => ($description !== '' ? $description : null),
      ':assignee_id' => (ctype_digit((string)$assigneeId) ? (int)$assigneeId : null),
      ':status_id'   => (ctype_digit((string)$statusId) ? (int)$statusId : null),
      ':category_id' => (ctype_digit((string)$categoryId) ? (int)$categoryId : null),
      ':due_date'    => ($dueDate !== '' ? $dueDate : null),
    ]);
    $newId = (int)$st->fetchColumn();

    $initComment = trim((string)p('comment',''));
    if ($initComment !== '') {
      $c = $pdo->prepare("INSERT INTO pm_ticket_comments(ticket_id, body, is_system) VALUES(:tid,:body,false)");
      $c->execute([':tid'=>$newId, ':body'=>$initComment]);
    }

    redirect('ticket.php?id=' . $newId);
  }

  if ($mode === 'update' && $isEdit) {
    $ticketId = (int)$id;

    $title = trim((string)p('title',''));
    $description = trim((string)p('description',''));
    $assigneeId = p('assignee_id','');
    $statusId   = p('status_id','');
    $categoryId = p('category_id','');
    $dueDate    = p('due_date','');
    $commentBody = trim((string)p('comment',''));

    if ($title === '') {
      $_SESSION['flash_error'] = 'タイトルは必須です';
      redirect('ticket.php?id=' . $ticketId);
    }

    $before = fetch_ticket($pdo, $ticketId);
    if (!$before) { http_response_code(404); echo "Not Found"; exit; }

    $changes = [];
    if ($before['title'] !== $title) $changes[] = "タイトルを変更";

    $beforeAssignee = (string)($before['assignee_id'] ?? '');
    $afterAssignee  = (ctype_digit((string)$assigneeId) ? (string)(int)$assigneeId : '');
    if ($beforeAssignee !== $afterAssignee) $changes[] = "担当者を変更";

    $beforeStatus = (string)($before['status_id'] ?? '');
    $afterStatus  = (ctype_digit((string)$statusId) ? (string)(int)$statusId : '');
    if ($beforeStatus !== $afterStatus) $changes[] = "状態を変更";

    $beforeCategory = (string)($before['category_id'] ?? '');
    $afterCategory  = (ctype_digit((string)$categoryId) ? (string)(int)$categoryId : '');
    if ($beforeCategory !== $afterCategory) $changes[] = "カテゴリを変更";

    $beforeDue = (string)($before['due_date'] ?? '');
    $afterDue  = (string)($dueDate ?? '');
    if ($beforeDue !== $afterDue) $changes[] = "期限を変更";

    $beforeDesc = (string)($before['description'] ?? '');
    if ($beforeDesc !== $description) $changes[] = "内容を更新";

    $pdo->beginTransaction();
    try {
      $up = $pdo->prepare("
        UPDATE pm_tickets
        SET title=:title,
            description=:description,
            assignee_id=:assignee_id,
            status_id=:status_id,
            category_id=:category_id,
            due_date=:due_date,
            updated_at=NOW()
        WHERE id=:id
      ");
      $up->execute([
        ':title' => $title,
        ':description' => ($description !== '' ? $description : null),
        ':assignee_id' => (ctype_digit((string)$assigneeId) ? (int)$assigneeId : null),
        ':status_id'   => (ctype_digit((string)$statusId) ? (int)$statusId : null),
        ':category_id' => (ctype_digit((string)$categoryId) ? (int)$categoryId : null),
        ':due_date'    => ($dueDate !== '' ? $dueDate : null),
        ':id' => $ticketId,
      ]);

      if ($changes) {
        $sys = $pdo->prepare("INSERT INTO pm_ticket_comments(ticket_id, body, is_system) VALUES(:tid,:body,true)");
        $sys->execute([':tid'=>$ticketId, ':body'=>implode(' / ', $changes)]);
      }

      if ($commentBody !== '') {
        $c = $pdo->prepare("INSERT INTO pm_ticket_comments(ticket_id, body, is_system) VALUES(:tid,:body,false)");
        $c->execute([':tid'=>$ticketId, ':body'=>$commentBody]);
      }

      $pdo->commit();
    } catch (Throwable $e) {
      $pdo->rollBack();
      throw $e;
    }

    redirect('ticket.php?id=' . $ticketId);
  }
}

$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= $isEdit ? "チケット詳細 #".h($id) : "新規チケット" ?></title>
  <link rel="stylesheet" href="assets/style.css">
  <script defer src="assets/app.js"></script>
</head>
<body class="app">

  <aside class="sidebar" id="sidebar">
    <div class="side-brand">PM Tool</div>
    <nav class="side-nav">
      <a class="side-link" href="tickets.php">チケット</a>
      <a class="side-link" href="admin.php">管理</a>
    </nav>
    <div class="side-actions">
      <a class="btn" href="tickets.php">一覧へ</a>
      <a class="btn primary" href="ticket.php">+ 新規チケット</a>
    </div>
  </aside>
  <div class="overlay" id="overlay"></div>

  <main class="content">
    <div class="mobile-topbar">
      <button type="button" class="menu-toggle" id="menuToggle" aria-label="メニューを開く">☰</button>
      <div class="mobile-title">PM Tool</div>
    </div>

    <div class="wrap">
      <section class="card">
        <div class="head-row">
          <h1><?= $isEdit ? "チケット詳細" : "新規チケット作成" ?></h1>
          <?php if ($isEdit): ?>
            <div class="muted mono">#<?=h($id)?></div>
          <?php endif; ?>
        </div>

        <?php if ($flashError): ?>
          <div class="alert error"><?=h($flashError)?></div>
        <?php endif; ?>

        <form method="post" class="ticket-form">
          <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
          <input type="hidden" name="mode" value="<?= $isEdit ? 'update' : 'create' ?>">

          <div class="grid two">
            <label>
              タイトル（必須）
              <input type="text" name="title" value="<?=h($ticket['title'] ?? '')?>" required>
            </label>

            <label>
              期限
              <input type="date" name="due_date" value="<?=h($ticket['due_date'] ?? '')?>">
            </label>

            <label>
              カテゴリ
              <select name="category_id">
                <option value="">（未設定）</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?=h($c['id'])?>" <?= (string)($ticket['category_id'] ?? '') === (string)$c['id'] ? 'selected' : '' ?>>
                    <?=h($c['name'])?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>

            <label>
              担当者
              <select name="assignee_id">
                <option value="">（未設定）</option>
                <?php foreach ($assignees as $a): ?>
                  <option value="<?=h($a['id'])?>" <?= (string)($ticket['assignee_id'] ?? '') === (string)$a['id'] ? 'selected' : '' ?>>
                    <?=h($a['name'])?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>

            <label>
              状態
              <select name="status_id">
                <option value="">（未設定）</option>
                <?php foreach ($statuses as $s): ?>
                  <option value="<?=h($s['id'])?>" <?= (string)($ticket['status_id'] ?? '') === (string)$s['id'] ? 'selected' : '' ?>>
                    <?=h($s['name'])?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>

          <label>
            内容
            <textarea name="description" rows="6" placeholder="タスクの詳細、背景、完了条件など"><?=h($ticket['description'] ?? '')?></textarea>
          </label>

          <label>
            スレッド投稿（更新メモ）
            <textarea name="comment" rows="3" placeholder="進捗・共有・変更理由など（任意）"></textarea>
          </label>

          <div class="actions">
            <button class="btn primary" type="submit"><?= $isEdit ? "更新する" : "作成する" ?></button>
            <?php if (!$isEdit): ?>
              <a class="btn ghost" href="tickets.php">キャンセル</a>
            <?php endif; ?>
          </div>
        </form>

        <?php if ($isEdit): ?>
          <hr class="sep">

          <h2>スレッド</h2>
          <div class="thread">
            <?php if (!$comments): ?>
              <div class="muted">まだ投稿がありません</div>
            <?php endif; ?>

            <?php foreach ($comments as $c): ?>
              <div class="comment <?= $c['is_system'] ? 'system' : '' ?>">
                <div class="meta mono">
                  <?=h(substr((string)$c['created_at'],0,19))?>
                  <?= $c['is_system'] ? ' / system' : '' ?>
                </div>
                <div class="body"><?= linkify_nl((string)$c['body']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </main>

</body>
</html>
