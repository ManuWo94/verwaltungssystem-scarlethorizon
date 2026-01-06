<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
if (!currentUserCan('admin', 'view') && $_SESSION['role'] !== 'Administrator') {
    header('Location: ' . getBasePath() . 'access_denied.php');
    exit;
}

$basePath = getBasePath();

// Daten laden
$articles = loadJsonData('help_articles.json');
$categories = loadJsonData('help_categories.json');

// AJAX Handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    // Artikel erstellen
    if ($action === 'create_article') {
        $newArticle = [
            'id' => 'art_' . uniqid(),
            'title' => $_POST['title'] ?? '',
            'category_id' => $_POST['category_id'] ?? '',
            'content' => $_POST['content'] ?? '',
            'keywords' => array_filter(array_map('trim', explode(',', $_POST['keywords'] ?? ''))),
            'related_articles' => $_POST['related_articles'] ?? [],
            'author' => $_SESSION['username'] ?? 'System',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => null,
            'published' => isset($_POST['published'])
        ];
        
        $articles[] = $newArticle;
        
        if (saveJsonData('help_articles.json', $articles)) {
            echo json_encode(['success' => true, 'message' => 'Artikel erfolgreich erstellt', 'article' => $newArticle]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern']);
        }
        exit;
    }
    
    // Artikel aktualisieren
    if ($action === 'update_article') {
        $articleId = $_POST['article_id'] ?? '';
        $updated = false;
        
        foreach ($articles as &$article) {
            if ($article['id'] === $articleId) {
                $article['title'] = $_POST['title'] ?? $article['title'];
                $article['category_id'] = $_POST['category_id'] ?? $article['category_id'];
                $article['content'] = $_POST['content'] ?? $article['content'];
                $article['keywords'] = array_filter(array_map('trim', explode(',', $_POST['keywords'] ?? '')));
                $article['related_articles'] = $_POST['related_articles'] ?? [];
                $article['updated_at'] = date('Y-m-d H:i:s');
                $article['published'] = isset($_POST['published']);
                $updated = true;
                break;
            }
        }
        
        if ($updated && saveJsonData('help_articles.json', $articles)) {
            echo json_encode(['success' => true, 'message' => 'Artikel aktualisiert']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Aktualisieren']);
        }
        exit;
    }
    
    // Artikel löschen
    if ($action === 'delete_article') {
        $articleId = $_POST['article_id'] ?? '';
        $articles = array_filter($articles, function($a) use ($articleId) {
            return $a['id'] !== $articleId;
        });
        $articles = array_values($articles);
        
        if (saveJsonData('help_articles.json', $articles)) {
            echo json_encode(['success' => true, 'message' => 'Artikel gelöscht']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Löschen']);
        }
        exit;
    }
    
    // Kategorie erstellen
    if ($action === 'create_category') {
        $newCategory = [
            'id' => 'cat_' . uniqid(),
            'name' => $_POST['name'] ?? '',
            'icon' => $_POST['icon'] ?? 'folder',
            'description' => $_POST['description'] ?? '',
            'order' => (int)($_POST['order'] ?? 999)
        ];
        
        $categories[] = $newCategory;
        
        if (saveJsonData('help_categories.json', $categories)) {
            echo json_encode(['success' => true, 'message' => 'Kategorie erstellt', 'category' => $newCategory]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern']);
        }
        exit;
    }
    
    // Kategorie aktualisieren
    if ($action === 'update_category') {
        $categoryId = $_POST['category_id'] ?? '';
        $updated = false;
        
        foreach ($categories as &$category) {
            if ($category['id'] === $categoryId) {
                $category['name'] = $_POST['name'] ?? $category['name'];
                $category['icon'] = $_POST['icon'] ?? $category['icon'];
                $category['description'] = $_POST['description'] ?? $category['description'];
                $category['order'] = (int)($_POST['order'] ?? $category['order']);
                $updated = true;
                break;
            }
        }
        
        if ($updated && saveJsonData('help_categories.json', $categories)) {
            echo json_encode(['success' => true, 'message' => 'Kategorie aktualisiert']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Aktualisieren']);
        }
        exit;
    }
    
    // Kategorie löschen
    if ($action === 'delete_category') {
        $categoryId = $_POST['category_id'] ?? '';
        
        // Prüfen ob Artikel in dieser Kategorie existieren
        $hasArticles = false;
        foreach ($articles as $article) {
            if ($article['category_id'] === $categoryId) {
                $hasArticles = true;
                break;
            }
        }
        
        if ($hasArticles) {
            echo json_encode(['success' => false, 'message' => 'Kategorie enthält noch Artikel']);
            exit;
        }
        
        $categories = array_filter($categories, function($c) use ($categoryId) {
            return $c['id'] !== $categoryId;
        });
        $categories = array_values($categories);
        
        if (saveJsonData('help_categories.json', $categories)) {
            echo json_encode(['success' => true, 'message' => 'Kategorie gelöscht']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Löschen']);
        }
        exit;
    }
    
    // Bild hochladen
    if ($action === 'upload_image') {
        if (isset($_FILES['image'])) {
            $file = $_FILES['image'];
            $uploadDir = __DIR__ . '/../uploads/help_images/';
            
            // Verzeichnis erstellen falls nicht vorhanden
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'help_' . uniqid() . '.' . $ext;
            $filepath = $uploadDir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $url = $basePath . 'uploads/help_images/' . $filename;
                echo json_encode(['success' => true, 'url' => $url]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Upload fehlgeschlagen']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Keine Datei']);
        }
        exit;
    }
}

// Nach Order sortieren
usort($categories, function($a, $b) {
    return ($a['order'] ?? 999) - ($b['order'] ?? 999);
});

?>

<style>
.admin-tabs {
    margin-bottom: 30px;
}

.admin-tabs .nav-link {
    border-radius: 8px 8px 0 0;
}

.article-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 15px;
    transition: all 0.2s;
}

.article-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.article-status {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.article-status.published {
    background: #d1e7dd;
    color: #0f5132;
}

.article-status.draft {
    background: #fff3cd;
    color: #664d03;
}

.category-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    background: #f8f9fa;
    border-radius: 12px;
    font-size: 12px;
}

.editor-toolbar {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 8px 8px 0 0;
    border: 1px solid #dee2e6;
    border-bottom: none;
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.editor-toolbar button {
    padding: 6px 12px;
    border: 1px solid #dee2e6;
    background: white;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
}

.editor-toolbar button:hover {
    background: #e9ecef;
}

.editor-content {
    border: 1px solid #dee2e6;
    border-radius: 0 0 8px 8px;
    min-height: 400px;
    padding: 15px;
    font-family: inherit;
}

.keyword-tag {
    display: inline-block;
    padding: 4px 10px;
    background: #0dcaf0;
    color: white;
    border-radius: 12px;
    font-size: 12px;
    margin: 2px;
}

.modal-lg {
    max-width: 900px;
}
</style>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i data-feather="edit"></i> Hilfe-Artikel verwalten
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="<?php echo $basePath; ?>modules/help.php" class="btn btn-sm btn-outline-secondary mr-2">
                        <i data-feather="eye"></i> Hilfe ansehen
                    </a>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs admin-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-toggle="tab" href="#articles">
                        <i data-feather="file-text"></i> Artikel (<?php echo count($articles); ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#categories">
                        <i data-feather="folder"></i> Kategorien (<?php echo count($categories); ?>)
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Artikel Tab -->
                <div id="articles" class="tab-pane fade show active">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Artikel verwalten</h5>
                            <button class="btn btn-primary btn-sm" onclick="showArticleModal()">
                                <i data-feather="plus"></i> Neuer Artikel
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($articles)): ?>
                                <div class="text-center py-5">
                                    <i data-feather="file-text" style="width: 64px; height: 64px; color: #6c757d;"></i>
                                    <p class="text-muted mt-3">Noch keine Artikel erstellt</p>
                                    <button class="btn btn-primary" onclick="showArticleModal()">
                                        Ersten Artikel erstellen
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="mb-3">
                                    <input type="text" class="form-control" id="searchArticles" placeholder="Artikel durchsuchen...">
                                </div>
                                <div id="articlesList">
                                    <?php foreach ($articles as $article): ?>
                                    <?php
                                    $category = null;
                                    foreach ($categories as $cat) {
                                        if ($cat['id'] === $article['category_id']) {
                                            $category = $cat;
                                            break;
                                        }
                                    }
                                    ?>
                                    <div class="article-card" data-article-id="<?php echo htmlspecialchars($article['id']); ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5>
                                                    <?php echo htmlspecialchars($article['title']); ?>
                                                    <span class="article-status <?php echo $article['published'] ? 'published' : 'draft'; ?>">
                                                        <?php echo $article['published'] ? 'Veröffentlicht' : 'Entwurf'; ?>
                                                    </span>
                                                </h5>
                                                <?php if ($category): ?>
                                                <span class="category-badge">
                                                    <i data-feather="<?php echo htmlspecialchars($category['icon']); ?>" style="width: 14px; height: 14px;"></i>
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </span>
                                                <?php endif; ?>
                                                <div class="mt-2">
                                                    <?php foreach ($article['keywords'] ?? [] as $keyword): ?>
                                                    <span class="keyword-tag"><?php echo htmlspecialchars($keyword); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                                <small class="text-muted d-block mt-2">
                                                    Erstellt: <?php echo date('d.m.Y H:i', strtotime($article['created_at'])); ?>
                                                    <?php if ($article['updated_at']): ?>
                                                    | Aktualisiert: <?php echo date('d.m.Y H:i', strtotime($article['updated_at'])); ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline-primary" onclick='editArticle(<?php echo json_encode($article); ?>)'>
                                                    <i data-feather="edit-2"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteArticle('<?php echo htmlspecialchars($article['id']); ?>')">
                                                    <i data-feather="trash-2"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Kategorien Tab -->
                <div id="categories" class="tab-pane fade">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Kategorien verwalten</h5>
                            <button class="btn btn-primary btn-sm" onclick="showCategoryModal()">
                                <i data-feather="plus"></i> Neue Kategorie
                            </button>
                        </div>
                        <div class="card-body">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Reihenfolge</th>
                                        <th>Icon</th>
                                        <th>Name</th>
                                        <th>Beschreibung</th>
                                        <th>Artikel</th>
                                        <th>Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $category): ?>
                                    <?php
                                    $articleCount = 0;
                                    foreach ($articles as $art) {
                                        if ($art['category_id'] === $category['id']) {
                                            $articleCount++;
                                        }
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo $category['order']; ?></td>
                                        <td><i data-feather="<?php echo htmlspecialchars($category['icon']); ?>"></i></td>
                                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                                        <td><?php echo htmlspecialchars($category['description']); ?></td>
                                        <td><span class="badge badge-secondary"><?php echo $articleCount; ?></span></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick='editCategory(<?php echo json_encode($category); ?>)'>
                                                <i data-feather="edit-2"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteCategory('<?php echo htmlspecialchars($category['id']); ?>')">
                                                <i data-feather="trash-2"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Artikel Modal -->
<div class="modal fade" id="articleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="articleModalTitle">Neuer Artikel</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="articleForm">
                    <input type="hidden" id="article_id" name="article_id">
                    
                    <div class="form-group">
                        <label>Titel *</label>
                        <input type="text" class="form-control" name="title" id="article_title" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Kategorie *</label>
                        <select class="form-control" name="category_id" id="article_category" required>
                            <option value="">-- Bitte wählen --</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['id']); ?>">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Keywords (komma-getrennt)</label>
                        <input type="text" class="form-control" name="keywords" id="article_keywords" 
                               placeholder="z.B. Lizenz, Beantragen, Erstellung">
                    </div>
                    
                    <div class="form-group">
                        <label>Inhalt *</label>
                        <div class="editor-toolbar">
                            <button type="button" onclick="insertFormat('h2')"><strong>H2</strong></button>
                            <button type="button" onclick="insertFormat('h3')"><strong>H3</strong></button>
                            <button type="button" onclick="insertFormat('b')"><strong>B</strong></button>
                            <button type="button" onclick="insertFormat('i')"><em>I</em></button>
                            <button type="button" onclick="insertFormat('ul')">• Liste</button>
                            <button type="button" onclick="insertFormat('ol')">1. Liste</button>
                            <button type="button" onclick="insertFormat('code')">Code</button>
                            <button type="button" onclick="insertFormat('blockquote')">Zitat</button>
                            <button type="button" onclick="showImageUpload()">🖼️ Bild</button>
                        </div>
                        <textarea class="form-control editor-content" name="content" id="article_content" required></textarea>
                        <small class="form-text text-muted">HTML-Formatierung möglich</small>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" name="published" id="article_published" value="1" checked>
                        <label class="form-check-label" for="article_published">
                            Veröffentlichen
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-primary" onclick="saveArticle()">Speichern</button>
            </div>
        </div>
    </div>
</div>

<!-- Kategorie Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="categoryModalTitle">Neue Kategorie</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="categoryForm">
                    <input type="hidden" id="category_id" name="category_id">
                    
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" class="form-control" name="name" id="category_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Icon (Feather Icon Name) *</label>
                        <input type="text" class="form-control" name="icon" id="category_icon" required placeholder="z.B. folder">
                        <small class="form-text text-muted">
                            <a href="https://feathericons.com/" target="_blank">Feather Icons anzeigen</a>
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label>Beschreibung</label>
                        <textarea class="form-control" name="description" id="category_description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Reihenfolge</label>
                        <input type="number" class="form-control" name="order" id="category_order" value="999">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-primary" onclick="saveCategory()">Speichern</button>
            </div>
        </div>
    </div>
</div>

<!-- Bild Upload Modal -->
<div class="modal fade" id="imageUploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bild hochladen</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="imageUploadForm">
                    <div class="form-group">
                        <input type="file" class="form-control-file" name="image" accept="image/*" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-primary" onclick="uploadImage()">Hochladen</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    feather.replace();
    
    // Artikel-Suche
    $('#searchArticles').on('keyup', function() {
        const query = $(this).val().toLowerCase();
        $('.article-card').each(function() {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.includes(query));
        });
    });
});

function showArticleModal(article = null) {
    if (article) {
        $('#articleModalTitle').text('Artikel bearbeiten');
        $('#article_id').val(article.id);
        $('#article_title').val(article.title);
        $('#article_category').val(article.category_id);
        $('#article_keywords').val((article.keywords || []).join(', '));
        $('#article_content').val(article.content);
        $('#article_published').prop('checked', article.published);
    } else {
        $('#articleModalTitle').text('Neuer Artikel');
        $('#articleForm')[0].reset();
        $('#article_id').val('');
    }
    $('#articleModal').modal('show');
    feather.replace();
}

function editArticle(article) {
    showArticleModal(article);
}

function saveArticle() {
    const formData = new FormData($('#articleForm')[0]);
    const articleId = $('#article_id').val();
    
    formData.append('action', articleId ? 'update_article' : 'create_article');
    if (articleId) {
        formData.append('article_id', articleId);
    }
    
    $.ajax({
        url: '',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                alert(response.message);
                location.reload();
            } else {
                alert('Fehler: ' + response.message);
            }
        },
        error: function() {
            alert('Ein Fehler ist aufgetreten');
        }
    });
}

function deleteArticle(articleId) {
    if (!confirm('Artikel wirklich löschen?')) return;
    
    $.post('', {
        action: 'delete_article',
        article_id: articleId
    }, function(response) {
        if (response.success) {
            location.reload();
        } else {
            alert('Fehler: ' + response.message);
        }
    }, 'json');
}

function showCategoryModal(category = null) {
    if (category) {
        $('#categoryModalTitle').text('Kategorie bearbeiten');
        $('#category_id').val(category.id);
        $('#category_name').val(category.name);
        $('#category_icon').val(category.icon);
        $('#category_description').val(category.description);
        $('#category_order').val(category.order);
    } else {
        $('#categoryModalTitle').text('Neue Kategorie');
        $('#categoryForm')[0].reset();
        $('#category_id').val('');
    }
    $('#categoryModal').modal('show');
}

function editCategory(category) {
    showCategoryModal(category);
}

function saveCategory() {
    const formData = $('#categoryForm').serialize();
    const categoryId = $('#category_id').val();
    
    $.post('', formData + '&action=' + (categoryId ? 'update_category' : 'create_category'), function(response) {
        if (response.success) {
            alert(response.message);
            location.reload();
        } else {
            alert('Fehler: ' + response.message);
        }
    }, 'json');
}

function deleteCategory(categoryId) {
    if (!confirm('Kategorie wirklich löschen?')) return;
    
    $.post('', {
        action: 'delete_category',
        category_id: categoryId
    }, function(response) {
        if (response.success) {
            location.reload();
        } else {
            alert('Fehler: ' + response.message);
        }
    }, 'json');
}

function insertFormat(type) {
    const textarea = document.getElementById('article_content');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    let replacement = '';
    
    switch(type) {
        case 'h2':
            replacement = '<h2>' + (selectedText || 'Überschrift') + '</h2>';
            break;
        case 'h3':
            replacement = '<h3>' + (selectedText || 'Überschrift') + '</h3>';
            break;
        case 'b':
            replacement = '<strong>' + (selectedText || 'Text') + '</strong>';
            break;
        case 'i':
            replacement = '<em>' + (selectedText || 'Text') + '</em>';
            break;
        case 'ul':
            replacement = '<ul>\n  <li>' + (selectedText || 'Listenpunkt') + '</li>\n</ul>';
            break;
        case 'ol':
            replacement = '<ol>\n  <li>' + (selectedText || 'Listenpunkt') + '</li>\n</ol>';
            break;
        case 'code':
            replacement = '<code>' + (selectedText || 'Code') + '</code>';
            break;
        case 'blockquote':
            replacement = '<blockquote>' + (selectedText || 'Zitat') + '</blockquote>';
            break;
    }
    
    textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
    textarea.focus();
}

function showImageUpload() {
    $('#imageUploadModal').modal('show');
}

function uploadImage() {
    const formData = new FormData($('#imageUploadForm')[0]);
    formData.append('action', 'upload_image');
    
    $.ajax({
        url: '',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                const imgTag = '<img src="' + response.url + '" alt="Bild" />';
                const textarea = document.getElementById('article_content');
                const pos = textarea.selectionStart;
                textarea.value = textarea.value.substring(0, pos) + imgTag + textarea.value.substring(pos);
                $('#imageUploadModal').modal('hide');
                $('#imageUploadForm')[0].reset();
            } else {
                alert('Fehler: ' + response.message);
            }
        },
        error: function() {
            alert('Upload fehlgeschlagen');
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
