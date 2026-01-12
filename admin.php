<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib.php';

$pdo = db();

function is_hex_color($s): bool {
  return preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string)$s) === 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();

  $type = p('type','');      // assignee | status | category | due_rule
  $action = p('action','');  // add | update

  if (!in_array($type, ['assignee','status','category','due_rule'], true)) {
    http_response_code(400); exit('Bad Request');
  }

  // ASSIGNEE
  if ($type === 'assignee') {
    $table = 'pm_assignees';

    if ($action === 'add') {
      $name = trim((string)p('name',''));
      $sort = (int)p('sort_order', 0);
      if ($name !== '') {
        $st = $pdo->prepare("INSERT INTO {$table}(name, sort_order, is_active, created_at, updated_at)
                             VALUES(:name, :sort, true, NOW(), NOW())");
        $st->execute([':name'=>$name, ':sort'=>$sort]);
      }
      redirect('admin.php');
    }

    if ($action === 'update') {
      $id = p('id','');
      $name = trim((string)p('name',''));
      $sort = (int)p('sort_order', 0);
      $active = (p('is_active','0') === '1');

      if (ctype_digit((string)$id) && $name !== '') {
        $st = $pdo->prepare("UPDATE {$table}
                             SET name=:name, sort_order=:sort, is_active=:active, updated_at=NOW()
                             WHERE id=:id");
        $st->execute([
          ':name'=>$name,
          ':sort'=>$sort,
          ':active'=>$active,
          ':id'=>(int)$id
        ]);
      }
      redirect('admin.php');
    }
  }

  // STATUS
  if ($type === 'status') {
    $table = 'pm_statuses';

    if ($action === 'add') {
      $name = trim((string)p('name',''));
      $sort = (int)p('sort_order', 0);
      $isDone = (p('is_done','0') === '1');

      if ($name !== '') {
        $st = $pdo->prepare("INSERT INTO {$table}(name, sort_order, is_active, is_done, created_at, updated_at)
                             VALUES(:name, :sort, true, :is_done, NOW(), NOW())");
        $st->execute([':name'=>$name, ':sort'=>$sort, ':is_done'=>$isDone]);
      }
      redirect('admin.php');
    }

    if ($action === 'update') {
      $id = p('id','');
      $name = trim((string)p('name',''));
      $sort = (int)p('sort_order', 0);
      $active = (p('is_active','0') === '1');
      $isDone  = (p('is_done','0') === '1');

      if (ctype_digit((string)$id) && $name !== '') {
        $st = $pdo->prepare("UPDATE {$table}
                             SET name=:name, sort_order=:sort, is_active=:active, is_done=:is_done, updated_at=NOW()
                             WHERE id=:id");
        $st->execute([
          ':name'=>$name,
          ':sort'=>$sort,
          ':active'=>$active,
          ':is_done'=>$isDone,
          ':id'=>(int)$id
        ]);
      }
      redirect('admin.php');
    }
  }

  // CATEGORY
  if ($type === 'category') {
    $table = 'pm_categories';

    if ($action === 'add') {
      $name = trim((string)p('name',''));
      $sort = (int)p('sort_order', 0);
      if ($name !== '') {
        $st = $pdo->prepare("INSERT INTO {$table}(name, sort_order, is_active, created_at, updated_at)
                             VALUES(:name, :sort, true, NOW(), NOW())");
        $st->execute([':name'=>$name, ':sort'=>$sort]);
      }
      redirect('admin.php');
    }

    if ($action === 'update') {
      $id = p('id','');
      $name = trim((string)p('name',''));
      $sort = (int)p('sort_order', 0);
      $active = (p('is_active','0') === '1');

      if (ctype_digit((string)$id) && $name !== '') {
        $st = $pdo->prepare("UPDATE {$table}
                             SET name=:name, sort_order=:sort, is_active=:active, updated_at=NOW()
                             WHERE id=:id");
        $st->execute([
          ':name'=>$name,
          ':sort'=>$sort,
          ':active'=>$active,
          ':id'=>(int)$id
        ]);
      }
      redirect('admin.php');
    }
  }

  // DUE RULE
  if ($type === 'due_rule') {
    if ($action === 'add') {
      $daysFrom = (int)p('days_from', 0);
      $daysTo   = (int)p('days_to', 0);
      $label    = trim((string)p('label', ''));
      $bg       = trim((string)p('bg_color', '#EEF2FF'));
      $fg       = trim((string)p('text_color', '#1E3A8A'));
      $bd       = trim((string)p('border_color', '#93C5FD'));
      $sort     = (int)p('sort_order', 0);
      $active   = (p('is_active','0') === '1');

      if ($label !== '' && $daysFrom >= 0 && $daysTo >= $daysFrom && is_hex_color($bg) && is_hex_color($fg) && is_hex_color($bd)) {
        $st = $pdo->prepare("
          INSERT INTO pm_due_rules(days_from, days_to, label, bg_color, text_color, border_color, is_active, sort_order, created_at, updated_at)
          VALUES(:df,:dt,:label,:bg,:fg,:bd,:active,:sort, NOW(), NOW())
        ");
        $st->execute([
          ':df'=>$daysFrom, ':dt'=>$daysTo, ':label'=>$label,
          ':bg'=>$bg, ':fg'=>$fg, ':bd'=>$bd,
          ':active'=>$active, ':sort'=>$sort
        ]);
      }
      redirect('admin.php');
    }

    if ($action === 'update') {
      $id = p('id','');
      $daysFrom = (int)p('days_from', 0);
      $daysTo   = (int)p('days_to', 0);
      $label    = trim((string)p('label', ''));
      $bg       = trim((string)p('bg_color', '#EEF2FF'));
      $fg       = trim((string)p('text_color', '#1E3A8A'));
      $bd       = trim((string)p('border_color', '#93C5FD'));
      $sort     = (int)p('sort_order', 0);
      $active   = (p('is_active','0') === '1');

      if (ctype_digit((string)$id) && $label !== '' && $daysFrom >= 0 && $daysTo >= $daysFrom && is_hex_color($bg) && is_hex_color($fg) && is_hex_color($bd)) {
        $st = $pdo->prepare("
          UPDATE pm_due_rules
          SET days_from=:df, days_to=:dt, label=:label,
              bg_color=:bg, text_color=:fg, border_color=:bd,
              is_active=:active, sort_order=:sort, updated_at=NOW()
          WHERE id=:id
        ");
        $st->execute([
          ':df'=>$daysFrom, ':dt'=>$daysTo, ':label'=>$label,
          ':bg'=>$bg, ':fg'=>$fg, ':bd'=>$bd,
          ':active'=>$active, ':sort'=>$sort,
          ':id'=>(int)$id
        ]);
      }
      redirect('admin.php');
    }
  }
}

// 一覧取得
$assignees = $pdo->query("SELECT * FROM pm_assignees ORDER BY sort_order, id")->fetchAll();
$statuses  = $pdo->query("SELECT * FROM pm_statuses  ORDER BY sort_order, id")->fetchAll();
$categories = $pdo->query("SELECT * FROM pm_categories ORDER BY sort_order, id")->fetchAll();
$dueRules  = $pdo->query("SELECT * FROM pm_due_rules ORDER BY sort_order, id")->fetchAll();
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>管理</title>
  <link rel="stylesheet" href="assets/style.css">
  <script defer src="assets/app.js"></script>
</head>
<body class="app">

  <aside class="sidebar" id="sidebar">
    <div class="side-brand">PM Tool</div>
    <nav class="side-nav">
      <a class="side-link" href="tickets.php">チケット</a>
      <a class="side-link is-active" href="admin.php">管理</a>
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
        <h1>管理画面</h1>
        <p class="muted">担当者・状態・カテゴリをセレクトに反映します。</p>

        <div class="admin-grid">
          <div>
            <h2>担当者</h2>

            <form method="post" class="inline-form">
              <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
              <input type="hidden" name="type" value="assignee">
              <input type="hidden" name="action" value="add">
              <input type="text" name="name" placeholder="追加する担当者名">
              <input type="number" name="sort_order" value="0" class="num">
              <button class="btn" type="submit">追加</button>
            </form>

            <div class="list">
              <?php foreach ($assignees as $a): ?>
                <form method="post" class="row">
                  <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
                  <input type="hidden" name="type" value="assignee">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="id" value="<?=h($a['id'])?>">
                  <input type="text" name="name" value="<?=h($a['name'])?>">
                  <input type="number" name="sort_order" value="<?=h($a['sort_order'])?>" class="num">
                  <label class="check">
                    <input type="checkbox" name="is_active" value="1" <?=$a['is_active'] ? 'checked' : ''?>>
                    有効
                  </label>
                  <button class="btn ghost" type="submit">保存</button>
                </form>
              <?php endforeach; ?>
            </div>
          </div>

          <div>
            <h2>状態</h2>

            <form method="post" class="inline-form">
              <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
              <input type="hidden" name="type" value="status">
              <input type="hidden" name="action" value="add">
              <input type="text" name="name" placeholder="追加する状態名">
              <input type="number" name="sort_order" value="0" class="num">
              <label class="check">
                <input type="checkbox" name="is_done" value="1">
                完了扱い
              </label>
              <button class="btn" type="submit">追加</button>
            </form>

            <div class="list">
              <?php foreach ($statuses as $s): ?>
                <form method="post" class="row">
                  <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
                  <input type="hidden" name="type" value="status">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="id" value="<?=h($s['id'])?>">
                  <input type="text" name="name" value="<?=h($s['name'])?>">
                  <input type="number" name="sort_order" value="<?=h($s['sort_order'])?>" class="num">

                  <label class="check">
                    <input type="checkbox" name="is_active" value="1" <?=$s['is_active'] ? 'checked' : ''?>>
                    有効
                  </label>

                  <label class="check">
                    <input type="checkbox" name="is_done" value="1" <?=(!empty($s['is_done']) ? 'checked' : '')?>>
                    完了扱い
                  </label>

                  <button class="btn ghost" type="submit">保存</button>
                </form>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <hr class="sep">

        <h2>カテゴリ</h2>

        <form method="post" class="inline-form">
          <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
          <input type="hidden" name="type" value="category">
          <input type="hidden" name="action" value="add">
          <input type="text" name="name" placeholder="追加するカテゴリ名">
          <input type="number" name="sort_order" value="0" class="num">
          <button class="btn" type="submit">追加</button>
        </form>

        <div class="list">
          <?php foreach ($categories as $c): ?>
            <form method="post" class="row">
              <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
              <input type="hidden" name="type" value="category">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?=h($c['id'])?>">
              <input type="text" name="name" value="<?=h($c['name'])?>">
              <input type="number" name="sort_order" value="<?=h($c['sort_order'])?>" class="num">
              <label class="check">
                <input type="checkbox" name="is_active" value="1" <?=$c['is_active'] ? 'checked' : ''?>>
                有効
              </label>
              <button class="btn ghost" type="submit">保存</button>
            </form>
          <?php endforeach; ?>
        </div>

        <hr class="sep">

        <h2>期限ラベル設定（残り日数で色付け）</h2>
        <p class="muted">「残り日数」が days_from〜days_to の範囲に入ったら指定色のバッジを表示します。期限切れは自動で赤表示です。</p>

        <form method="post" class="inline-form">
          <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
          <input type="hidden" name="type" value="due_rule">
          <input type="hidden" name="action" value="add">

          <input type="number" name="days_from" class="num" value="0" min="0" placeholder="from">
          <input type="number" name="days_to" class="num" value="0" min="0" placeholder="to">
          <input type="text" name="label" placeholder="ラベル（例：期限間近）">

          <input type="text" name="bg_color" class="num" value="#EEF2FF" placeholder="#RRGGBB">
          <input type="text" name="text_color" class="num" value="#1E3A8A" placeholder="#RRGGBB">
          <input type="text" name="border_color" class="num" value="#93C5FD" placeholder="#RRGGBB">

          <label class="check">
            <input type="checkbox" name="is_active" value="1" checked> 有効
          </label>

          <input type="number" name="sort_order" class="num" value="0" placeholder="並び">
          <button class="btn" type="submit">追加</button>
        </form>

        <div class="list">
          <?php foreach ($dueRules as $r): ?>
            <form method="post" class="row">
              <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
              <input type="hidden" name="type" value="due_rule">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?=h($r['id'])?>">

              <input type="number" name="days_from" class="num" value="<?=h($r['days_from'])?>" min="0">
              <input type="number" name="days_to" class="num" value="<?=h($r['days_to'])?>" min="0">
              <input type="text" name="label" value="<?=h($r['label'])?>">

              <input type="text" name="bg_color" class="num" value="<?=h($r['bg_color'])?>">
              <input type="text" name="text_color" class="num" value="<?=h($r['text_color'])?>">
              <input type="text" name="border_color" class="num" value="<?=h($r['border_color'])?>">

              <label class="check">
                <input type="checkbox" name="is_active" value="1" <?=(!empty($r['is_active'])?'checked':'')?>>
                有効
              </label>

              <input type="number" name="sort_order" class="num" value="<?=h($r['sort_order'])?>">
              <button class="btn ghost" type="submit">保存</button>

              <?php
                $style = "--due-bg:".h($r['bg_color']).";--due-fg:".h($r['text_color']).";--due-bd:".h($r['border_color']).";";
              ?>
              <span class="due-tag" style="<?=$style?>">プレビュー</span>
            </form>
          <?php endforeach; ?>
        </div>

      </section>
    </div>
  </main>

</body>
</html>
