<?php
define('BASE_URL', '');

require_once 'config/database.php';
require_once 'includes/auth.php';

$currentPage = 'alert';
$pageTitle   = 'Alert Stok';

requireOwner();
$db = getDB();

$habis = $db->query("SELECT * FROM produk WHERE stok=0 ORDER BY model,warna")->fetch_all(MYSQLI_ASSOC);
$mnp   = $db->query("SELECT * FROM produk WHERE stok>0 AND stok<stok_minimum ORDER BY stok ASC")->fetch_all(MYSQLI_ASSOC);

// Kelompokkan per model supaya gampang dipindai pemilik, tanpa mengubah data atau query dasarnya
$habisPerModel = [];
foreach ($habis as $p) { $habisPerModel[$p['model']][] = $p; }

$mnpPerModel = [];
foreach ($mnp as $p) { $mnpPerModel[$p['model']][] = $p; }
// Urutkan grup model berdasarkan stok paling menipis duluan
uasort($mnpPerModel, fn($a, $b) => $a[0]['stok'] <=> $b[0]['stok']);

$wh = [
    'Hitam' => '#1C1C1C', 'Navy'  => '#1E2D4E',
    'Cream' => '#D8C9A8', 'Army'  => '#4A5733',
    'Sage'  => '#7A9A82', 'Merah' => '#B83232', 'Pink' => '#D4728A'
];

require_once 'includes/header.php';
?>

<div class="card">
    <div class="card-head">
        <h2>Alert Stok</h2>
    </div>

    <div class="alert-wrap">

        <!-- Stok Habis -->
        <div class="sdiv">
            Kritis (Stok Habis <?= count($habis) ?> SKU)
        </div>

        <?php if (!empty($habisPerModel)): ?>
            <?php foreach ($habisPerModel as $modelName => $items): ?>
                <?php $swatch = array_values(array_unique(array_column($items, 'warna'))); ?>
                <details class="model-group critical">
                    <summary>
                        <span class="mg-main">
                            <span class="mg-arrow"></span>
                            <span class="mg-name"><?= htmlspecialchars($modelName) ?></span>
                        </span>
                        <span class="mg-right">
                            <span class="mg-swatches">
                                <?php foreach (array_slice($swatch, 0, 6) as $w): ?>
                                    <span class="mg-dot" style="background:<?= $wh[$w] ?? '#ccc' ?>" title="<?= htmlspecialchars($w) ?>"></span>
                                <?php endforeach; ?>
                            </span>
                            <span class="mg-count"><?= count($items) ?> SKU</span>
                        </span>
                    </summary>

                    <?php foreach ($items as $p): ?>
                        <?php $ukl = $p['ukuran'] !== 'All Size' ? ' ' . $p['ukuran'] : ''; ?>

                        <a href="produk.php?model=<?= urlencode($p['model']) ?>&warna=<?= urlencode($p['warna']) ?>" class="ai danger" style="text-decoration:none;color:inherit">
                            <div>
                                <div class="at">
                                    <?= $p['kode_sku'] ?> · <?= htmlspecialchars($p['model']) ?> ·
                                    <span class="wdot">
                                        <span style="background:<?= $wh[$p['warna']] ?? '#ccc' ?>"></span>
                                        <?= $p['warna'] ?>
                                    </span>
                                    <?= $ukl ?>
                                </div>
                                <div class="as">
                                    Stok: 0 unit · Minimum: <?= $p['stok_minimum'] ?> unit · Restock segera
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </details>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="font-size:12px;color:var(--hint)">Tidak ada stok yang habis. ✓</p>
        <?php endif; ?>

        <!-- Stok Menipis -->
        <div class="sdiv" style="padding-top:12px">
            Peringatan (Stok Menipis <?= count($mnp) ?> SKU)
        </div>

        <?php if (!empty($mnpPerModel)): ?>
            <?php foreach ($mnpPerModel as $modelName => $items): ?>
                <?php $swatch = array_values(array_unique(array_column($items, 'warna'))); ?>
                <details class="model-group warning">
                    <summary>
                        <span class="mg-main">
                            <span class="mg-arrow"></span>
                            <span class="mg-name"><?= htmlspecialchars($modelName) ?></span>
                        </span>
                        <span class="mg-right">
                            <span class="mg-swatches">
                                <?php foreach (array_slice($swatch, 0, 6) as $w): ?>
                                    <span class="mg-dot" style="background:<?= $wh[$w] ?? '#ccc' ?>" title="<?= htmlspecialchars($w) ?>"></span>
                                <?php endforeach; ?>
                            </span>
                            <span class="mg-count"><?= count($items) ?> SKU</span>
                        </span>
                    </summary>

                    <?php foreach ($items as $p): ?>
                        <?php $ukl = $p['ukuran'] !== 'All Size' ? ' ' . $p['ukuran'] : ''; ?>

                        <a href="produk.php?model=<?= urlencode($p['model']) ?>&warna=<?= urlencode($p['warna']) ?>" class="ai warn" style="text-decoration:none;color:inherit">
                            <div>
                                <div class="at">
                                    <?= $p['kode_sku'] ?> · <?= htmlspecialchars($p['model']) ?> ·
                                    <span class="wdot">
                                        <span style="background:<?= $wh[$p['warna']] ?? '#ccc' ?>"></span>
                                        <?= $p['warna'] ?>
                                    </span>
                                    <?= $ukl ?>
                                </div>
                                <div class="as">
                                    Stok: <?= $p['stok'] ?> unit · Minimum: <?= $p['stok_minimum'] ?> unit ·
                                    Defisit: <?= $p['stok'] - $p['stok_minimum'] ?> unit
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </details>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="font-size:12px;color:var(--hint)">Semua stok dalam kondisi aman.</p>
        <?php endif; ?>

    </div>
</div>

<style>
.model-group {
    border: 1px solid var(--c300);
    border-radius: var(--r10);
    margin-bottom: 10px;
    overflow: hidden;
    transition: box-shadow .15s ease;
}
.model-group[open] {
    box-shadow: 0 2px 10px rgba(59,42,21,.06);
}
.model-group summary {
    list-style: none;
    cursor: pointer;
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    font-weight: 500;
    transition: background .12s ease;
}
.model-group summary::-webkit-details-marker { display: none; }

.model-group.critical summary {
    background: var(--dbg);
}
.model-group.critical summary:hover { background: #F7D9D3; }

.model-group.warning summary {
    background: var(--wbg);
}
.model-group.warning summary:hover { background: #F2E2BE; }

.mg-main { display: flex; align-items: center; gap: 10px; }
.mg-name { color: var(--brown); font-weight: 600; }

.mg-arrow {
    width: 0; height: 0;
    border-top: 4px solid transparent;
    border-bottom: 4px solid transparent;
    border-left: 5px solid var(--hint);
    transition: transform .15s ease;
    flex-shrink: 0;
}
.model-group[open] .mg-arrow { transform: rotate(90deg); }

.mg-right { display: flex; align-items: center; gap: 10px; }
.mg-swatches { display: flex; gap: 3px; }
.mg-dot {
    width: 9px; height: 9px;
    border-radius: 50%;
    border: 1px solid rgba(0,0,0,.12);
}
.mg-count {
    font-size: 11px;
    color: var(--muted);
    background: rgba(255,255,255,.6);
    padding: 2px 8px;
    border-radius: 20px;
    font-weight: 600;
    min-width: 44px;
    text-align: center;
}

.model-group .ai {
    border-radius: 0;
    border: none;
    border-top: 1px solid var(--c200);
}
.model-group .ai:hover { background: rgba(0,0,0,.015); }
</style>

<?php require_once 'includes/footer.php'; ?>