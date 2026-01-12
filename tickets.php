<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib.php';

$pdo = db();

// フィルタ用マスタ
$assignees = $pdo->query("SELECT id, name FROM pm_assignees WHERE is_active=true ORDER BY sort_order, id")->fetchAll();
$statuses  = $pdo->query("SELECT id, name, is_done FROM pm_statuses WHERE is_active=true ORDER BY sort_order, id")->fetchAll();
$categories = $pdo->query("SELECT id, name FROM pm_categories WHERE is_active=true ORDER BY sort_order, id")->fetchAll();

$dueRules = $pdo->query("
  SELECT *
  FROM pm_due_rules
  WHERE is_active=true
  ORDER BY sort_order, id
")->fetchAll();

// 検索条件
$keyword     = trim((string)q('keyword', ''));
$assigneeId  = q('assignee_id', '');
$statusId    = q('status_id', '');
$categoryId  = q('category_id', '');
$dueFrom     = q('due_from', '');
$dueTo       = q('due_to', '');

// 完了非表示（デフォルトON）
$hideDone = q('hide_done', '1'); // '1' or '0'

// ソート
$allowedSort = [
  'due_date'  => 't.due_date',
  'assignee'  => 'a.name',
  'status'    => 's.name',
  'category'  => 'c.name',
  'updated'   => 't.updated_at',
  'created'   => 't.created_at',
];
$sort = q('sort', 'updated');
$dir  = strtolower((string)q('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
$sortCol = $allowedSort[$sort] ?? $allowedSort['updated'];

// ページング
$page = max(1, (int)q('page', 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($keyword !== '') {
  $where[] = "(t.title ILIKE :kw OR t.description ILIKE :kw)";
  $params[':kw'] = '%' . $keyword . '%';
}
if ($assigneeId !== '' && ctype_digit((string)$assigneeId)) {
  $where[] = "t.assignee_id = :assignee_id";
  $params[':assignee_id'] = (int)$assigneeId;
}
if ($statusId !== '' && ctype_digit((string)$statusId)) {
  $where[] = "t.status_id = :status_id";
  $params[':status_id'] = (int)$statusId;
}
if ($categoryId !== '' && ctype_digit((string)$categoryId)) {
  $where[] = "t.category_id = :category_id";
  $params[':category_id'] = (int)$categoryId;
}
if ($dueFrom !== '') {
  $where[] = "t.due_date >= :due_from";
  $params[':due_from'] = $dueFrom;
}
if ($dueTo !== '') {
  $where[] = "t.due_date <= :due_to";
  $params[':due_to'] = $dueTo;
}

// 完了(is_done=true)を非表示（checkbox OFF ならこの条件は付かない）
if ($hideDone === '1') {
  $where[] = "(t.status_id IS NULL OR COALESCE(s.is_done,false) = false)";
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// 件数
$countSql = "
  SELECT COUNT(*) AS cnt
  FROM pm_tickets t
  LEFT JOIN pm_assignees a ON a.id=t.assignee_id
  LEFT JOIN pm_statuses  s ON s.id=t.status_id
  LEFT JOIN pm_categories c ON c.id=t.category_id
  $whereSql
";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// 一覧取得（PostgreSQL: LIMIT/OFFSET）
$listSql = "
  SELECT
    t.id, t.title, t.due_date, t.updated_at, t.created_at,
    a.name AS assignee_name,
    s.name AS status_name,
    c.name AS category_name,
    COALESCE(s.is_done,false) AS is_done
  FROM pm_tickets t
  LEFT JOIN pm_assignees a ON a.id=t.assignee_id
  LEFT JOIN pm_statuses  s ON s.id=t.status_id
  LEFT JOIN pm_categories c ON c.id=t.category_id
  $whereSql
  ORDER BY $sortCol $dir
  LIMIT :perPage OFFSET :offset
";
$stmt = $pdo->prepare($listSql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$tickets = $stmt->fetchAll();

function build_query(array $overrides = []): string {
  $q = $_GET;
  foreach ($overrides as $k => $v) $q[$k] = $v;
  return http_build_query($q);
}
function sort_link($key, $label) {
  $currentSort = q('sort', 'updated');
  $currentDir  = q('dir', 'desc');
  $dir = 'asc';
  if ($currentSort === $key && strtolower((string)$currentDir) === 'asc') $dir = 'desc';
  $qs = build_query(['sort'=>$key, 'dir'=>$dir, 'page'=>1]);
  return '<a href="tickets.php?' . h($qs) . '" class="sortlink">' . h($label) . '</a>';
}

function due_badge(array $t, array $dueRules): string {
  if (empty($t['due_date'])) return '—';

  $dueTs = strtotime($t['due_date'] . ' 00:00:00');
  $todayTs = strtotime(date('Y-m-d') . ' 00:00:00');
  $daysLeft = (int)floor(($dueTs - $todayTs) / 86400);

  if ($daysLeft < 0) {
    $d = abs($daysLeft);
    $style = "--due-bg:#FEF2F2;--due-fg:#991B1B;--due-bd:#FCA5A5;";
    return '<span class="due-tag" style="'.$style.'">期限切れ '.$d.'日</span>';
  }

  foreach ($dueRules as $r) {
    if ($daysLeft >= (int)$r['days_from'] && $daysLeft <= (int)$r['days_to']) {
      $style = "--due-bg:".h($r['bg_color']).";--due-fg:".h($r['text_color']).";--due-bd:".h($r['border_color']).";";
      return '<span class="due-tag" style="'.$style.'">'.h($r['label']).' '.$daysLeft.'日</span>';
    }
  }

  return '<span class="due-tag">残り '.$daysLeft.'日</span>';
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>チケット一覧</title>
  <link rel="stylesheet" href="assets/style.css">
  <script defer src="assets/app.js"></script>
</head>
<body class="app">

  <aside class="sidebar" id="sidebar">
    <div class="side-brand">PM Tool</div>
    <nav class="side-nav">
      <a class="side-link is-active" href="tickets.php">チケット</a>
      <a class="side-link" href="admin.php">管理</a>
    </nav>
    <div class="side-actions">
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
        <h1>チケット一覧</h1>

        <form class="filters" method="get" action="tickets.php">
          <!-- チェックOFFでも値が送られるようにする -->
          <input type="hidden" name="hide_done" value="0">

          <div class="grid">
            <label>
              キーワード
              <input type="text" name="keyword" value="<?=h($keyword)?>" placeholder="タイトル/内容">
            </label>

            <label>
              担当者
              <select name="assignee_id">
                <option value="">（すべて）</option>
                <?php foreach ($assignees as $a): ?>
                  <option value="<?=h($a['id'])?>" <?=((string)$assigneeId===(string)$a['id'])?'selected':''?>>
                    <?=h($a['name'])?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>

            <label>
              状態
              <select name="status_id">
                <option value="">（すべて）</option>
                <?php foreach ($statuses as $s): ?>
                  <option value="<?=h($s['id'])?>" <?=((string)$statusId===(string)$s['id'])?'selected':''?>>
                    <?=h($s['name'])?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>

            <label>
              カテゴリ
              <select name="category_id">
                <option value="">（すべて）</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?=h($c['id'])?>" <?=((string)$categoryId===(string)$c['id'])?'selected':''?>>
                    <?=h($c['name'])?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>

            <label>
              期限（from）
              <input type="date" name="due_from" value="<?=h($dueFrom)?>">
            </label>

            <label>
              期限（to）
              <input type="date" name="due_to" value="<?=h($dueTo)?>">
            </label>

            <label class="check" style="align-self:end; padding-bottom:2px;">
              <input type="checkbox" name="hide_done" value="1" <?=($hideDone==='1')?'checked':''?>>
              完了を非表示
            </label>

            <div class="filter-actions">
              <button class="btn" type="submit">検索</button>
              <!-- キャッシュも消す -->
              <a class="btn ghost" href="tickets.php?reset=1">クリア</a>
            </div>
          </div>

          <input type="hidden" name="sort" value="<?=h($sort)?>">
          <input type="hidden" name="dir" value="<?=h($dir)?>">
        </form>

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th><?=sort_link('created','作成')?></th>
                <th>タイトル</th>
                <th><?=sort_link('category','カテゴリ')?></th>
                <th><?=sort_link('assignee','担当者')?></th>
                <th><?=sort_link('status','状態')?></th>
                <th><?=sort_link('due_date','期限')?></th>
                <th><?=sort_link('updated','更新')?></th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$tickets): ?>
                <tr><td colspan="7" class="muted">該当するチケットがありません</td></tr>
              <?php endif; ?>

              <?php foreach ($tickets as $t): ?>
                <?php
                  $dueClass = '';
                  if (!empty($t['due_date'])) {
                    $due = strtotime($t['due_date']);
                    $today = strtotime(date('Y-m-d'));
                    if ($due < $today) $dueClass = 'is-overdue';
                  }
                ?>
                <tr>
                  <td class="mono"><?=h(substr((string)$t['created_at'],0,10))?></td>
                  <td>
                    <a class="link" href="ticket.php?id=<?=h($t['id'])?>">
                      <?=h($t['title'])?>
                    </a>
                  </td>
                  <td><?=h($t['category_name'] ?? '—')?></td>
                  <td><?=h($t['assignee_name'] ?? '—')?></td>
                  <td><span class="pill"><?=h($t['status_name'] ?? '—')?></span></td>
                  <td>
                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                      <span class="<?=h($dueClass)?>"><?=h($t['due_date'] ?? '—')?></span>
                      <?= due_badge($t, $dueRules) ?>
                    </div>
                  </td>
                  <td class="mono"><?=h(substr((string)$t['updated_at'],0,16))?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="pager">
          <div class="muted">全 <?=$total?> 件 / <?=$page?> / <?=$totalPages?></div>
          <div class="pager-links">
            <?php if ($page > 1): ?>
              <a class="btn ghost" href="tickets.php?<?=h(build_query(['page'=>$page-1]))?>">← 前へ</a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
              <a class="btn ghost" href="tickets.php?<?=h(build_query(['page'=>$page+1]))?>">次へ →</a>
            <?php endif; ?>
          </div>
        </div>

      </section>
    </div>
  </main>

</body>
</html>
