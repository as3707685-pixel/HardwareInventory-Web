<?php
/*
    EARLY STAGE CUSTOMER WEBSITE

    This first version is only a public product catalog.
    Login, registration, ordering, order history, and cancellation will be added
    through the commits described in NOTES/COMMIT_GUIDE.md.
*/

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$bootstrapError = '';
$message = '';
$messageType = 'info';
$rows = [];
$totalAll = 0;
$availableCount = 0;

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function status_class($status) {
    return strtolower(str_replace(' ', '-', trim((string)$status)));
}

function load_inventory($db) {
    $q = trim($_GET['q'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $status = trim($_GET['status'] ?? '');

    $sql = "SELECT id, item_name, category, brand, model, quantity, status, location, remarks, updated_at
            FROM hardware_items
            WHERE " . public_inventory_sql();

    $params = [];

    if ($q !== '') {
        $sql .= " AND (item_name LIKE :q OR category LIKE :q OR brand LIKE :q OR model LIKE :q OR status LIKE :q OR location LIKE :q)";
        $params[':q'] = '%' . $q . '%';
    }

    if ($category !== '') {
        $sql .= " AND category = :category";
        $params[':category'] = $category;
    }

    if ($status !== '') {
        $sql .= " AND status = :status";
        $params[':status'] = $status;
    }

    $sql .= " ORDER BY CASE status WHEN 'Available' THEN 0 WHEN 'Low Stock' THEN 1 ELSE 2 END, item_name ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

try {
    $db = get_db();
    seed_if_empty($db);

    $rows = load_inventory($db);
    $totalAll = (int)$db->query("SELECT COUNT(*) FROM hardware_items WHERE " . public_inventory_sql())->fetchColumn();
    $availableCount = (int)$db->query("SELECT COUNT(*) FROM hardware_items WHERE " . public_inventory_sql() . " AND quantity > 0 AND status IN ('Available', 'Low Stock')")->fetchColumn();
} catch (Exception $ex) {
    $bootstrapError = $ex->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hardware Shop Portal - Early Stage</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="page-shell">
    <div class="ambient ambient-one"></div>
    <div class="ambient ambient-two"></div>
    <main class="container">
        <?php if ($bootstrapError !== ''): ?>
            <section class="panel login-card">
                <h1>System Setup</h1>
                <div class="notice error"><?= e($bootstrapError) ?></div>
                <p class="muted">Enable PDO SQLite in PHP, then restart the server.</p>
            </section>
        <?php else: ?>
            <header class="topbar">
                <div>
                    <span class="eyebrow">Hardware Store</span>
                    <h1>Public Inventory Catalog</h1>
                    <p class="muted">Early-stage version: browsing is available, but customer login and ordering are not added yet.</p>
                </div>
            </header>

            <?php if ($message !== ''): ?><div class="notice page-notice <?= e($messageType) ?>"><?= e($message) ?></div><?php endif; ?>

            <section class="stats-grid">
                <div class="panel stat-card"><span>Catalog Items</span><strong><?= e($totalAll) ?></strong></div>
                <div class="panel stat-card"><span>Ready to Order Later</span><strong><?= e($availableCount) ?></strong></div>
                <div class="panel stat-card"><span>Customer Login</span><strong>TODO</strong></div>
                <div class="panel stat-card"><span>Orders</span><strong>TODO</strong></div>
            </section>

            <section class="panel inventory-panel">
                <div class="section-title">
                    <div>
                        <h2>Product Inventory</h2>
                        <p class="muted small">Browse hardware items. Ordering will be added in the next commits.</p>
                    </div>
                    <span class="pill"><?= e(count($rows)) ?> shown</span>
                </div>

                <form class="filters" method="get" action="index.php">
                    <input type="search" name="q" placeholder="Search item, brand, model, location" value="<?= e($_GET['q'] ?? '') ?>">
                    <select name="category">
                        <option value="">All Categories</option>
                        <?php foreach (inventory_categories() as $cat): ?>
                            <option value="<?= e($cat) ?>" <?= (($_GET['category'] ?? '') === $cat) ? 'selected' : '' ?>><?= e($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status">
                        <option value="">All Statuses</option>
                        <?php foreach (['Available', 'Low Stock', 'In Use', 'For Repair'] as $stat): ?>
                            <option value="<?= e($stat) ?>" <?= (($_GET['status'] ?? '') === $stat) ? 'selected' : '' ?>><?= e($stat) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Apply</button>
                    <a class="btn secondary" href="index.php">Reset</a>
                </form>

                <div class="cards-grid shop-grid">
                    <?php if (!$rows): ?>
                        <div class="empty-card">No matching items found.</div>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <article class="item-card shop-card">
                                <div class="item-head">
                                    <div>
                                        <h3><?= e($row['item_name']) ?></h3>
                                        <p><?= e(trim(($row['brand'] ?? '') . ' ' . ($row['model'] ?? ''))) ?></p>
                                    </div>
                                    <span class="status <?= e(status_class($row['status'])) ?>"><?= e($row['status']) ?></span>
                                </div>

                                <div class="item-meta">
                                    <span>Category<br><strong><?= e($row['category']) ?></strong></span>
                                    <span>Stock<br><strong><?= e($row['quantity']) ?></strong></span>
                                    <span>Location<br><strong><?= e($row['location']) ?></strong></span>
                                </div>

                                <?php if (trim($row['remarks'] ?? '') !== ''): ?>
                                    <p class="remarks"><?= e($row['remarks']) ?></p>
                                <?php endif; ?>

                                <div class="unavailable">Order form will be added in a later commit.</div>
                                <small>Updated <?= e($row['updated_at']) ?></small>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
