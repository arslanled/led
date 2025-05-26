<?php
require_once 'config.php';
check_auth();

// Kullanıcının izinlerini kontrol et ve güvenli hale getir
$izinli_sutunlar = [
    'isim' => has_permission('isim'),
    'bolge' => has_permission('bolge'),
    'durum' => has_permission('durum'),
    'aciklama' => has_permission('aciklama'),
    'telefon' => has_permission('telefon'),
    'gayrimenkul_tipi' => has_permission('gayrimenkul_tipi'),
    'fotoğraflar' => has_permission('fotoğraflar'),
    'tarih' => has_permission('tarih')
];

// Arama parametrelerini güvenli şekilde al
$search_query = isset($_GET['arama']) ? clean_input($_GET['arama']) : '';
$search_conditions = [];
$params = [];

if (!empty($search_query)) {
    foreach ($izinli_sutunlar as $column => $has_permission) {
        if ($has_permission && $column !== 'fotoğraflar') {
            $search_conditions[] = "$column LIKE ?";
            $params[] = "%$search_query%";
        }
    }
}

// Sıralama parametrelerini güvenli şekilde al
$order_by = 'tarih DESC';
$allowed_columns = array_keys(array_filter($izinli_sutunlar));

if (isset($_GET['sort'], $_GET['order']) && in_array($_GET['sort'], $allowed_columns)) {
    $order_by = clean_input($_GET['sort']) . ' ' . clean_input($_GET['order']);
}

// SQL sorgusunu güvenli şekilde oluştur
$selected_columns = array_merge(['id'], $allowed_columns);
$sql = "SELECT " . implode(", ", $selected_columns) . " FROM portfoyler";

if (!empty($search_conditions)) {
    $sql .= " WHERE " . implode(" OR ", $search_conditions);
}
$sql .= " ORDER BY $order_by";

// Sorguyu çalıştır
try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $portfoyler = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Sorgu hatası: " . $e->getMessage());
}

// Sütun görünürlük ayarları
$visible_columns = array_filter($izinli_sutunlar);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portföy Listesi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .navbar-brand img { height: 30px; }
        .table-responsive { overflow-x: auto; }
        .thumbnail { 
            max-width: 60px; 
            max-height: 60px;
            cursor: pointer; 
            transition: transform 0.2s;
            object-fit: cover;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }
        .thumbnail:hover { transform: scale(1.1); }
        #imageModal img { max-width: 100%; max-height: 80vh; }
        .sort-icon { margin-left: 5px; }
        .no-permission { color: #6c757d; font-style: italic; }
        .column-toggle { 
            display: flex; 
            align-items: center; 
            margin-bottom: 10px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .column-toggle label { 
            margin-left: 8px;
            margin-bottom: 0;
            cursor: pointer;
        }
        .column-toggle input[type="checkbox"] {
            cursor: pointer;
        }
        .hidden-column {
            display: none !important;
        }
        .column-selector {
            margin-bottom: 15px;
        }
        .sortable-header {
            cursor: pointer;
        }
        .sortable-header:hover {
            background-color: #f1f1f1;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <div class="card shadow">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Portföy Listesi</h5>
                    <div>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                            <i class="bi bi-funnel"></i> Filtreler
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="collapse" id="filterCollapse">
                <div class="card-body border-bottom">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-8">
                            <input type="text" class="form-control" name="arama" placeholder="Arama..." 
                                   value="<?= htmlspecialchars($search_query) ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Ara</button>
                        </div>
                        <div class="col-md-2">
                            <a href="arama.php" class="btn btn-outline-secondary w-100">Sıfırla</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card-body">
                <!-- Sütun seçici -->
                <div class="column-selector mb-3">
                    <h6>Sütun Seçimi:</h6>
                    <div class="d-flex flex-wrap">
                        <?php foreach ($visible_columns as $column => $has_permission): ?>
                            <?php if ($has_permission): ?>
                                <div class="column-toggle me-3">
                                    <input type="checkbox" id="toggle_<?= $column ?>" checked 
                                           data-column="<?= $column ?>">
                                    <label for="toggle_<?= $column ?>"><?= ucfirst($column) ?></label>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($_SESSION['admin']): ?>
                            <div class="column-toggle me-3">
                                <input type="checkbox" id="toggle_islemler" checked 
                                       data-column="islemler">
                                <label for="toggle_islemler">İşlemler</label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <?php foreach ($visible_columns as $column => $has_permission): ?>
                                    <?php if ($has_permission): ?>
                                        <th class="column-<?= $column ?> sortable-header" data-column="<?= $column ?>">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span><?= ucfirst($column) ?></span>
                                                <?php if (isset($_GET['sort']) && $_GET['sort'] === $column): ?>
                                                    <i class="bi bi-arrow-<?= $_GET['order'] === 'ASC' ? 'up' : 'down' ?> sort-icon"></i>
                                                <?php endif; ?>
                                            </div>
                                        </th>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if ($_SESSION['admin']): ?>
                                    <th class="column-islemler">İşlemler</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($portfoyler)): ?>
                                <tr>
                                    <td colspan="<?= count($visible_columns) + ($_SESSION['admin'] ? 1 : 0) ?>" class="text-center">
                                        Kayıt bulunamadı
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($portfoyler as $portfoy): ?>
                                    <tr>
                                        <?php foreach ($visible_columns as $column => $has_permission): ?>
                                            <?php if ($has_permission): ?>
                                                <td class="column-<?= $column ?>">
                                                    <?php if ($column === 'durum'): ?>
                                                        <span class="badge bg-<?= 
                                                            $portfoy['durum'] === 'Satılık' ? 'success' : 
                                                            ($portfoy['durum'] === 'Kiralık' ? 'warning' : 'info') 
                                                        ?>">
                                                            <?= htmlspecialchars($portfoy['durum'] ?? 'Belirtilmemiş') ?>
                                                        </span>
                                                    <?php elseif ($column === 'fotoğraflar'): ?>
                                                        <?php
                                                        $photos = [];
                                                        if (!empty($portfoy['fotoğraflar'])) {
                                                            $photos = json_decode($portfoy['fotoğraflar'], true) ?: [];
                                                        }
                                                        if (!empty($photos)): ?>
                                                            <?php foreach ($photos as $photo): ?>
                                                                <img src="https://www.arslanproperty.com/portfoyum1/<?= htmlspecialchars($photo) ?>" 
                                                                     class="thumbnail me-1" 
                                                                     onclick="openModal(this.src)"
                                                                     alt="Portföy görseli">
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <span class="no-permission">Fotoğraf yok</span>
                                                        <?php endif; ?>
                                                    <?php elseif ($column === 'tarih'): ?>
                                                        <?= !empty($portfoy['tarih']) ? date('d.m.Y H:i', strtotime($portfoy['tarih'])) : 'Belirtilmemiş' ?>
                                                    <?php else: ?>
                                                        <?= !empty($portfoy[$column]) ? htmlspecialchars($portfoy[$column]) : 'Belirtilmemiş' ?>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        
                                        <?php if ($_SESSION['admin']): ?>
                                            <td class="column-islemler">
                                                <a href="portfoy_duzenle.php?id=<?= $portfoy['id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary"
                                                   title="Düzenle">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="portfoy_sil.php?id=<?= $portfoy['id'] ?>" 
                                                   class="btn btn-sm btn-outline-danger" 
                                                   onclick="return confirm('Bu kaydı silmek istediğinize emin misiniz?')"
                                                   title="Sil">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Resim Gösterim Modalı -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Fotoğraf Görüntüle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" class="img-fluid" alt="Büyütülmüş portföy görseli">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sıralama işlevselliği
        document.querySelectorAll('.sortable-header').forEach(header => {
            header.addEventListener('click', function() {
                const column = this.dataset.column;
                sortTable(column);
            });
        });

        function sortTable(column) {
            const urlParams = new URLSearchParams(window.location.search);
            let order = 'ASC';
            
            if (urlParams.get('sort') === column && urlParams.get('order') === 'ASC') {
                order = 'DESC';
            }
            
            urlParams.set('sort', column);
            urlParams.set('order', order);
            window.location.href = `arama.php?${urlParams.toString()}`;
        }
        
        function openModal(src) {
            const modal = new bootstrap.Modal(document.getElementById('imageModal'));
            document.getElementById('modalImage').src = src;
            modal.show();
        }

        // Sütun gizleme/gösterme işlevselliği
        document.querySelectorAll('.column-toggle input[type="checkbox"]').forEach(checkbox => {
            // Başlangıç durumunu kontrol et
            const column = checkbox.dataset.column;
            const isChecked = localStorage.getItem(`column_${column}_visible`) !== 'false';
            
            if (!isChecked) {
                checkbox.checked = false;
                toggleColumnVisibility(column, false);
            }
            
            // Değişiklikleri dinle
            checkbox.addEventListener('change', function() {
                const isVisible = this.checked;
                toggleColumnVisibility(column, isVisible);
                localStorage.setItem(`column_${column}_visible`, isVisible);
            });
        });

        function toggleColumnVisibility(column, isVisible) {
            document.querySelectorAll(`.column-${column}`).forEach(element => {
                if (isVisible) {
                    element.classList.remove('hidden-column');
                } else {
                    element.classList.add('hidden-column');
                }
            });
        }
    </script>
</body>
</html>