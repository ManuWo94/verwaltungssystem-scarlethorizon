<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth.php';

checkLogin();

$basePath = getBasePath();

// Daten laden
$articles = loadJsonData('help_articles.json');
$categories = loadJsonData('help_categories.json');

// Nach Order sortieren
usort($categories, function($a, $b) {
    return ($a['order'] ?? 999) - ($b['order'] ?? 999);
});

// Artikel nach Kategorie gruppieren
$articlesByCategory = [];
foreach ($articles as $article) {
    $catId = $article['category_id'] ?? 'uncategorized';
    if (!isset($articlesByCategory[$catId])) {
        $articlesByCategory[$catId] = [];
    }
    $articlesByCategory[$catId][] = $article;
}

// Einzelnen Artikel anzeigen?
$viewArticleId = $_GET['article'] ?? null;
$currentArticle = null;
if ($viewArticleId) {
    foreach ($articles as $article) {
        if ($article['id'] === $viewArticleId) {
            $currentArticle = $article;
            break;
        }
    }
}

?>

<style>
.help-container {
    max-width: 1400px;
    margin: 0 auto;
}

.help-sidebar {
    position: sticky;
    top: 80px;
    max-height: calc(100vh - 100px);
    overflow-y: auto;
}

.help-category {
    margin-bottom: 20px;
}

.help-category-header {
    background: #f8f9fa;
    padding: 12px 16px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    transition: all 0.2s;
}

.help-category-header:hover {
    background: #e9ecef;
}

.help-category-header.active {
    background: var(--primary, #0e1214);
    color: white;
}

.help-article-list {
    padding-left: 20px;
    margin-top: 10px;
}

.help-article-item {
    padding: 8px 12px;
    border-left: 2px solid transparent;
    cursor: pointer;
    transition: all 0.2s;
    display: block;
    color: inherit;
    text-decoration: none;
}

.help-article-item:hover {
    border-left-color: var(--primary, #0e1214);
    background: #f8f9fa;
    color: inherit;
    text-decoration: none;
}

.help-article-item.active {
    border-left-color: var(--primary, #0e1214);
    background: #e9ecef;
    font-weight: 600;
}

.help-search-box {
    margin-bottom: 20px;
}

.help-search-box input {
    border-radius: 8px;
    padding: 12px 16px;
    border: 2px solid #e9ecef;
}

.help-search-box input:focus {
    border-color: var(--primary, #0e1214);
    box-shadow: none;
}

.help-content {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.help-article-header {
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 20px;
    margin-bottom: 30px;
}

.help-article-meta {
    display: flex;
    gap: 20px;
    margin-top: 10px;
    font-size: 14px;
    color: #6c757d;
}

.help-article-body {
    line-height: 1.8;
    font-size: 16px;
}

.help-article-body h2 {
    margin-top: 30px;
    margin-bottom: 15px;
    font-size: 24px;
    font-weight: 600;
}

.help-article-body h3 {
    margin-top: 25px;
    margin-bottom: 12px;
    font-size: 20px;
    font-weight: 600;
}

.help-article-body img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    margin: 20px 0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.help-article-body code {
    background: #f8f9fa;
    padding: 2px 6px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 14px;
}

.help-article-body pre {
    background: #f8f9fa;
    padding: 16px;
    border-radius: 8px;
    overflow-x: auto;
    border-left: 4px solid var(--primary, #0e1214);
}

.help-article-body ul,
.help-article-body ol {
    margin: 15px 0;
    padding-left: 30px;
}

.help-article-body li {
    margin: 8px 0;
}

.help-article-body blockquote {
    border-left: 4px solid #0dcaf0;
    padding: 12px 20px;
    background: #e7f7ff;
    border-radius: 0 8px 8px 0;
    margin: 20px 0;
}

.help-breadcrumb {
    display: flex;
    gap: 8px;
    align-items: center;
    font-size: 14px;
    color: #6c757d;
    margin-bottom: 20px;
}

.help-breadcrumb a {
    color: #0dcaf0;
    text-decoration: none;
}

.help-breadcrumb a:hover {
    text-decoration: underline;
}

.help-toc {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.help-toc-title {
    font-weight: 600;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.help-toc ul {
    list-style: none;
    padding-left: 0;
}

.help-toc li {
    margin: 6px 0;
}

.help-toc a {
    color: inherit;
    text-decoration: none;
}

.help-toc a:hover {
    color: var(--primary, #0e1214);
}

.search-highlight {
    background: yellow;
    padding: 2px 4px;
    border-radius: 3px;
}

.no-results {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.category-card {
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
    cursor: pointer;
    transition: all 0.2s;
}

.category-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-color: var(--primary, #0e1214);
}

.category-icon {
    width: 48px;
    height: 48px;
    background: var(--primary, #0e1214);
    color: white;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
}

.article-count {
    display: inline-block;
    background: #e9ecef;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.back-to-overview {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #6c757d;
    text-decoration: none;
    margin-bottom: 20px;
}

.back-to-overview:hover {
    color: var(--primary, #0e1214);
    text-decoration: none;
}

.related-articles {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-top: 40px;
}

.related-articles h4 {
    margin-bottom: 15px;
}

.related-article-link {
    display: block;
    padding: 10px;
    color: inherit;
    text-decoration: none;
    border-radius: 6px;
}

.related-article-link:hover {
    background: white;
    color: inherit;
}
</style>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i data-feather="book-open"></i> Hilfe & Anleitungen
                </h1>
                <?php if (currentUserCan('admin', 'view') || $_SESSION['role'] === 'Administrator'): ?>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="<?php echo $basePath; ?>modules/help_admin.php" class="btn btn-sm btn-primary">
                        <i data-feather="edit"></i> Artikel verwalten
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <div class="help-container">
                <div class="row">
                    <!-- Sidebar mit Kategorien -->
                    <div class="col-md-3">
                        <div class="help-sidebar">
                            <!-- Suchfeld -->
                            <div class="help-search-box">
                                <input type="text" class="form-control" id="helpSearch" placeholder="Hilfe durchsuchen...">
                            </div>

                            <!-- Kategorien -->
                            <div id="categoryList">
                                <?php foreach ($categories as $category): ?>
                                <div class="help-category">
                                    <div class="help-category-header" data-category="<?php echo htmlspecialchars($category['id']); ?>">
                                        <i data-feather="<?php echo htmlspecialchars($category['icon']); ?>"></i>
                                        <span><?php echo htmlspecialchars($category['name']); ?></span>
                                    </div>
                                    <div class="help-article-list" style="display: none;">
                                        <?php 
                                        $catArticles = $articlesByCategory[$category['id']] ?? [];
                                        if (empty($catArticles)):
                                        ?>
                                        <small class="text-muted ml-3">Keine Artikel</small>
                                        <?php else: ?>
                                            <?php foreach ($catArticles as $article): ?>
                                            <a href="?article=<?php echo htmlspecialchars($article['id']); ?>" 
                                               class="help-article-item <?php echo ($currentArticle && $currentArticle['id'] === $article['id']) ? 'active' : ''; ?>"
                                               data-article-id="<?php echo htmlspecialchars($article['id']); ?>"
                                               data-title="<?php echo htmlspecialchars($article['title']); ?>"
                                               data-keywords="<?php echo htmlspecialchars(implode(' ', $article['keywords'] ?? [])); ?>">
                                                <?php echo htmlspecialchars($article['title']); ?>
                                            </a>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Hauptinhalt -->
                    <div class="col-md-9">
                        <?php if ($currentArticle): ?>
                            <!-- Artikel anzeigen -->
                            <div class="help-content">
                                <a href="<?php echo $basePath; ?>modules/help.php" class="back-to-overview">
                                    <i data-feather="arrow-left"></i> Zurück zur Übersicht
                                </a>

                                <?php
                                // Breadcrumb
                                $articleCategory = null;
                                foreach ($categories as $cat) {
                                    if ($cat['id'] === $currentArticle['category_id']) {
                                        $articleCategory = $cat;
                                        break;
                                    }
                                }
                                ?>
                                <div class="help-breadcrumb">
                                    <a href="<?php echo $basePath; ?>modules/help.php">Hilfe</a>
                                    <span>/</span>
                                    <?php if ($articleCategory): ?>
                                    <span><?php echo htmlspecialchars($articleCategory['name']); ?></span>
                                    <span>/</span>
                                    <?php endif; ?>
                                    <span><?php echo htmlspecialchars($currentArticle['title']); ?></span>
                                </div>

                                <div class="help-article-header">
                                    <h1><?php echo htmlspecialchars($currentArticle['title']); ?></h1>
                                    <div class="help-article-meta">
                                        <span>
                                            <i data-feather="user"></i>
                                            <?php echo htmlspecialchars($currentArticle['author'] ?? 'System'); ?>
                                        </span>
                                        <span>
                                            <i data-feather="calendar"></i>
                                            <?php echo date('d.m.Y', strtotime($currentArticle['created_at'] ?? 'now')); ?>
                                        </span>
                                        <?php if (!empty($currentArticle['updated_at'])): ?>
                                        <span>
                                            <i data-feather="edit-2"></i>
                                            Aktualisiert: <?php echo date('d.m.Y', strtotime($currentArticle['updated_at'])); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="help-article-body">
                                    <?php echo $currentArticle['content']; ?>
                                </div>

                                <?php if (!empty($currentArticle['related_articles'])): ?>
                                <div class="related-articles">
                                    <h4><i data-feather="link"></i> Verwandte Artikel</h4>
                                    <?php foreach ($currentArticle['related_articles'] as $relatedId): ?>
                                        <?php
                                        $relatedArticle = null;
                                        foreach ($articles as $art) {
                                            if ($art['id'] === $relatedId) {
                                                $relatedArticle = $art;
                                                break;
                                            }
                                        }
                                        if ($relatedArticle):
                                        ?>
                                        <a href="?article=<?php echo htmlspecialchars($relatedId); ?>" class="related-article-link">
                                            <i data-feather="arrow-right"></i> <?php echo htmlspecialchars($relatedArticle['title']); ?>
                                        </a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <!-- Kategorieübersicht -->
                            <div class="help-content">
                                <h2 class="mb-4">Willkommen im Hilfebereich</h2>
                                <p class="lead mb-4">Hier finden Sie Anleitungen und Erklärungen zu allen Funktionen des Systems.</p>

                                <div id="categoryOverview">
                                    <?php foreach ($categories as $category): ?>
                                    <?php
                                    $catArticles = $articlesByCategory[$category['id']] ?? [];
                                    ?>
                                    <div class="category-card" onclick="window.location.href='#'; toggleCategory('<?php echo htmlspecialchars($category['id']); ?>');">
                                        <div class="category-icon">
                                            <i data-feather="<?php echo htmlspecialchars($category['icon']); ?>" style="width: 24px; height: 24px;"></i>
                                        </div>
                                        <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                                        <p class="text-muted"><?php echo htmlspecialchars($category['description']); ?></p>
                                        <span class="article-count"><?php echo count($catArticles); ?> Artikel</span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <?php if (empty($articles)): ?>
                                <div class="no-results">
                                    <i data-feather="book" style="width: 64px; height: 64px; margin-bottom: 20px;"></i>
                                    <h4>Noch keine Hilfe-Artikel</h4>
                                    <p>Es wurden noch keine Artikel erstellt.</p>
                                    <?php if (currentUserCan('admin', 'view') || $_SESSION['role'] === 'Administrator'): ?>
                                    <a href="<?php echo $basePath; ?>modules/help_admin.php" class="btn btn-primary mt-3">
                                        Ersten Artikel erstellen
                                    </a>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
$(document).ready(function() {
    feather.replace();
    
    // Kategorie toggle
    $('.help-category-header').click(function() {
        const $this = $(this);
        const $list = $this.next('.help-article-list');
        
        // Toggle
        $list.slideToggle(200);
        $this.toggleClass('active');
    });
    
    // Kategorie aus Übersicht öffnen
    window.toggleCategory = function(categoryId) {
        const $header = $('.help-category-header[data-category="' + categoryId + '"]');
        const $list = $header.next('.help-article-list');
        
        // Alle schließen
        $('.help-article-list').slideUp(200);
        $('.help-category-header').removeClass('active');
        
        // Diese öffnen
        $list.slideDown(200);
        $header.addClass('active');
        
        // Scroll to
        $('html, body').animate({
            scrollTop: $header.offset().top - 100
        }, 300);
    };
    
    // Suche
    let searchTimeout;
    $('#helpSearch').on('keyup', function() {
        clearTimeout(searchTimeout);
        const query = $(this).val().toLowerCase().trim();
        
        searchTimeout = setTimeout(function() {
            if (query === '') {
                // Reset
                $('.help-article-item').show();
                $('.help-category').show();
                return;
            }
            
            // Durchsuchen
            let hasResults = false;
            
            $('.help-category').each(function() {
                const $category = $(this);
                let categoryHasResults = false;
                
                $category.find('.help-article-item').each(function() {
                    const $item = $(this);
                    const title = $item.data('title').toLowerCase();
                    const keywords = ($item.data('keywords') || '').toLowerCase();
                    
                    if (title.includes(query) || keywords.includes(query)) {
                        $item.show();
                        categoryHasResults = true;
                        hasResults = true;
                    } else {
                        $item.hide();
                    }
                });
                
                if (categoryHasResults) {
                    $category.show();
                    $category.find('.help-article-list').slideDown(200);
                    $category.find('.help-category-header').addClass('active');
                } else {
                    $category.hide();
                }
            });
            
            if (!hasResults && query !== '') {
                // Könnte hier eine "Keine Ergebnisse" Nachricht anzeigen
            }
        }, 300);
    });
    
    // Wenn ein Artikel angezeigt wird, Kategorie öffnen
    <?php if ($currentArticle && isset($currentArticle['category_id'])): ?>
    toggleCategory('<?php echo $currentArticle['category_id']; ?>');
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
