<?php
require_once dirname(__DIR__) . '/includes/init.php';
requireAdmin();

$adminPage  = 'portfolio';
$adminTitle = 'Case Study Editor';

$id   = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$item = $id ? getPortfolioById($id) : null;
if ($id && !$item) {
    setFlash('err', 'Case study not found.');
    header('Location: portfolio.php');
    exit;
}

$categories = array();
foreach (pieServices() as $svc) { $categories[] = $svc['name']; }
$categories[] = 'Growth & AI Automation';

/** Parse "value | label" lines into the stats JSON structure. */
function parseStatsLines($text)
{
    $out = array();
    foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
        $line = trim($line);
        if ($line === '') { continue; }
        $parts = explode('|', $line, 2);
        $out[] = array(
            'value' => trim($parts[0]),
            'label' => isset($parts[1]) ? trim($parts[1]) : '',
        );
    }
    return $out;
}
function statsToLines($json)
{
    $stats = jsonCol($json);
    $lines = array();
    foreach ($stats as $stat) {
        if (is_array($stat)) {
            $lines[] = $stat['value'] . (isset($stat['label']) && $stat['label'] !== '' ? ' | ' . $stat['label'] : '');
        } else {
            $lines[] = (string) $stat;
        }
    }
    return implode("\n", $lines);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        setFlash('err', 'Security token expired.');
    } else {
        $client   = sanitize(isset($_POST['client_name']) ? $_POST['client_name'] : '');
        $category = sanitize(isset($_POST['service_category']) ? $_POST['service_category'] : '');
        $challenge = sanitizeMultiline(isset($_POST['challenge']) ? $_POST['challenge'] : '');
        $strategy  = sanitizeMultiline(isset($_POST['strategy']) ? $_POST['strategy'] : '');
        $results   = sanitizeMultiline(isset($_POST['results']) ? $_POST['results'] : '');
        $statsJson = json_encode(parseStatsLines(isset($_POST['stats_lines']) ? $_POST['stats_lines'] : ''), JSON_UNESCAPED_UNICODE);
        $chartRaw  = trim(isset($_POST['chart_json']) ? (string) $_POST['chart_json'] : '');
        $chartJson = '';
        $chartErr  = false;
        if ($chartRaw !== '') {
            $decoded = json_decode($chartRaw, true);
            if (is_array($decoded) && isset($decoded['labels']) && isset($decoded['datasets'])) {
                $chartJson = json_encode($decoded, JSON_UNESCAPED_UNICODE);
            } else {
                $chartErr = true;
            }
        }
        $testimonial = sanitizeMultiline(isset($_POST['testimonial']) ? $_POST['testimonial'] : '');
        $tAuthor     = sanitize(isset($_POST['testimonial_author']) ? $_POST['testimonial_author'] : '');
        $order       = isset($_POST['display_order']) ? (int) $_POST['display_order'] : 0;
        $active      = isset($_POST['is_active']) ? 1 : 0;

        if ($client === '' || $category === '') {
            setFlash('err', 'Client name and service category are required.');
        } elseif ($chartErr) {
            setFlash('err', 'Chart JSON is invalid — expected {"labels":[...],"datasets":[...]}.');
        } else {
            $slugBase = slugify($client . '-' . $category);
            $slug = $slugBase;
            $n = 2;
            while (dbOne('SELECT id FROM portfolio WHERE slug = ? AND id <> ?', array($slug, $id))) { $slug = $slugBase . '-' . $n; $n++; }

            $thumb = $item && !empty($item['thumbnail']) ? $item['thumbnail'] : '';
            $up = uploadFile('thumbnail', 'portfolio', array('jpg', 'jpeg', 'png', 'webp'));
            if (!$up['ok']) {
                setFlash('err', $up['error']);
            } else {
                if ($up['path'] !== '') { if ($thumb !== '') { deleteUpload($thumb); } $thumb = $up['path']; }
                if ($item) {
                    dbExec('UPDATE portfolio SET client_name=?, service_category=?, slug=?, thumbnail=?, challenge=?, strategy=?, results=?, stats_json=?, chart_data_json=?, testimonial=?, testimonial_author=?, display_order=?, is_active=? WHERE id=?',
                        array($client, $category, $slug, $thumb, $challenge, $strategy, $results, $statsJson, $chartJson, $testimonial, $tAuthor, $order, $active, $id));
                    setFlash('ok', 'Case study updated.');
                } else {
                    dbInsert('INSERT INTO portfolio (client_name, service_category, slug, thumbnail, challenge, strategy, results, stats_json, chart_data_json, testimonial, testimonial_author, display_order, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                        array($client, $category, $slug, $thumb, $challenge, $strategy, $results, $statsJson, $chartJson, $testimonial, $tAuthor, $order, $active));
                    setFlash('ok', 'Case study created.');
                }
                header('Location: portfolio.php');
                exit;
            }
        }
    }
}

require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<form method="post" enctype="multipart/form-data" class="a-grid cols-2" style="align-items:start">
    <?= csrfField() ?>
    <div class="a-card">
        <h3>Case study</h3>
        <div class="a-field-row">
            <div class="a-field">
                <label for="pClient">Client name</label>
                <input id="pClient" name="client_name" type="text" required maxlength="150" value="<?= esc($item ? $item['client_name'] : '') ?>">
            </div>
            <div class="a-field">
                <label for="pCat">Service category</label>
                <select id="pCat" name="service_category" required>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= esc($cat) ?>"<?= $item && $item['service_category'] === $cat ? ' selected' : '' ?>><?= esc($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="a-field">
            <label for="pThumb">Thumbnail image</label>
            <input id="pThumb" name="thumbnail" type="file" accept="image/jpeg,image/png,image/webp">
            <div class="hint"><?= $item && $item['thumbnail'] !== '' ? 'Current: ' . esc($item['thumbnail']) : '16:10 works best.' ?></div>
        </div>
        <div class="a-field">
            <label for="pChallenge">Challenge</label>
            <textarea id="pChallenge" name="challenge"><?= esc($item ? $item['challenge'] : '') ?></textarea>
        </div>
        <div class="a-field">
            <label for="pStrategy">Strategy</label>
            <textarea id="pStrategy" name="strategy"><?= esc($item ? $item['strategy'] : '') ?></textarea>
        </div>
        <div class="a-field">
            <label for="pResults">Execution &amp; results</label>
            <textarea id="pResults" name="results"><?= esc($item ? $item['results'] : '') ?></textarea>
        </div>
    </div>

    <div class="a-grid" style="gap:20px">
        <div class="a-card">
            <h3>Result stats</h3>
            <div class="a-field">
                <label for="pStats">One per line: VALUE | LABEL</label>
                <textarea id="pStats" name="stats_lines" style="min-height:110px" placeholder="4.2× | ROAS in 90 days&#10;68% | lower cost per lead"><?= esc($item ? statsToLines($item['stats_json']) : '') ?></textarea>
            </div>
            <div class="a-field">
                <label for="pChart">Chart data (JSON for Chart.js)</label>
                <textarea id="pChart" name="chart_json" class="mono" style="min-height:130px" placeholder='{"labels":["W1","W2","W3","W4"],"datasets":[{"label":"Revenue","data":[1200,1900,2600,4100]}]'><?= esc($item ? $item['chart_data_json'] : '') ?></textarea>
                <div class="hint">Format: {"labels":[…],"datasets":[{"label":…,"data":[…]}]} — leave empty for no chart.</div>
            </div>
        </div>

        <div class="a-card">
            <h3>Testimonial &amp; publishing</h3>
            <div class="a-field">
                <label for="pQuote">Client quote</label>
                <textarea id="pQuote" name="testimonial" style="min-height:90px"><?= esc($item ? $item['testimonial'] : '') ?></textarea>
            </div>
            <div class="a-field-row">
                <div class="a-field">
                    <label for="pAuthor">Quote author</label>
                    <input id="pAuthor" name="testimonial_author" type="text" maxlength="150" value="<?= esc($item ? $item['testimonial_author'] : '') ?>">
                </div>
                <div class="a-field">
                    <label for="pOrder">Display order</label>
                    <input id="pOrder" name="display_order" type="number" min="0" value="<?= (int) ($item ? $item['display_order'] : 0) ?>">
                </div>
            </div>
            <div class="a-field">
                <label class="a-check"><input type="checkbox" name="is_active" value="1"<?= !$item || $item['is_active'] ? ' checked' : '' ?>> Active (visible on site)</label>
            </div>
            <div class="a-toolbar">
                <button class="a-btn primary" type="submit"><?= icon('check', 16) ?> Save Case Study</button>
                <a class="a-btn" href="portfolio.php">Back</a>
            </div>
        </div>
    </div>
</form>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
