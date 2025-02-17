<?php
// ------------------------------
// Configuration & Helpers
// ------------------------------
const UPLOADS_PARENT_DIR = 'uploads';
function getBaseDir() {
    $dir = isset($_GET['dir']) && !empty($_GET['dir']) ? basename($_GET['dir']) : 'default';
    $baseDir = UPLOADS_PARENT_DIR . '/' . $dir;
    is_dir($baseDir) or mkdir($baseDir, 0755, true);
    return $baseDir;
}
function filePath($filename) {
     return getBaseDir() . '/' . $filename;
}
function getEmailsFile() {return filePath('Emails.txt');}
function getAdressesNonValideFile() {return filePath('adressesNonValides.txt');}
function getEmailsTriesFile() { return filePath('EmailsT.txt'); }
function logError($msg) { error_log("[".date('Y-m-d H:i:s')."] $msg"); }

// ------------------------------
// Core Functions
// ------------------------------
function verifierEmail($adresse) { return filter_var($adresse, FILTER_VALIDATE_EMAIL); }
function chargerEmails($fichier) { return file_exists($fichier) ? array_map('trim', file($fichier, FILE_IGNORE_NEW_LINES)) : []; }
function enregistrerEmails($fichier, $emails) { 
    file_put_contents($fichier, implode("\n", $emails)."\n") or logError("Failed to write $fichier"); 
}
function displayCurrentEmails() {
    ob_start();
    echo "<h4>Contenu actuel de " . getEmailsFile() . " :</h4>";
    echo "<pre>" . (file_exists(getEmailsFile()) ? file_get_contents(getEmailsFile()) : "Aucun email enregistré.") . "</pre>";
    return ob_get_clean();
}
function listDownloadLinks() {
    $baseDir = getBaseDir(); $html = "";
    if (is_dir($baseDir)) {
        $files = array_filter(scandir($baseDir), function($f){ return $f !== '.' && $f !== '..'; });
        if ($files) {
            $html .= "<h3>Télécharger les fichiers générés</h3><ul>";
            foreach ($files as $f) { 
                if ($f!=="Emails.txt"){
                    $html .= '<li><a href="' . htmlspecialchars($baseDir.'/'.$f) . '" download>' . htmlspecialchars($f) . '</a></li>';
                };
            }
            $html .= "</ul>";
        }
    }
    return $html;
}

// ------------------------------
// Action Functions (1–6)
// ------------------------------
function processAction1() {
    $emails = chargerEmails(getEmailsFile());
    $valid = []; $invalid = [];
    foreach ($emails as $email) {
        verifierEmail($email) ? $valid[] = strtolower($email) : $invalid[] = $email;
    }
    enregistrerEmails(getEmailsFile(), $valid);
    if ($invalid) enregistrerEmails(getAdressesNonValideFile(), $invalid);
    $msg = "Nettoyage effectué : " . count($invalid) . " non valides (dans " . getAdressesNonValideFile() . ") et " . count($valid) . " valides conservées dans " . getEmailsFile();
    ob_start();
    echo "<h4>Contenu de " . getEmailsFile() . " :</h4><pre>" . file_get_contents(getEmailsFile()) . "</pre>";
    if (file_exists(getAdressesNonValideFile())) echo "<h4>Contenu de " . getAdressesNonValideFile() . " :</h4><pre>" . file_get_contents(getAdressesNonValideFile()) . "</pre>";
    return [$msg, ob_get_clean()];
}
function processAction2() {
    $freq = array_count_values(array_map('strtolower', chargerEmails(getEmailsFile())));
    ob_start(); ?>
    <h3>Tableau des adresses emails et leur fréquence</h3>
    <table class="table table-bordered">
      <thead><tr><th>Email</th><th>Fréquence</th></tr></thead>
      <tbody>
      <?php foreach ($freq as $email => $count): ?>
        <tr><td><?= $email ?></td><td><?= $count ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php return ["", ob_get_clean()];
}
function processAction3() {
    $emails = array_unique(array_map('strtolower', chargerEmails(getEmailsFile())));
    sort($emails);
    enregistrerEmails(getEmailsTriesFile(), $emails);
    $msg = "Emails dédoublonnés et triés, enregistrés dans " . getEmailsTriesFile() . " (".count($emails)." emails).";
    ob_start();
    echo "<h4>Contenu de " . getEmailsTriesFile() . " :</h4><pre>" . file_get_contents(getEmailsTriesFile()) . "</pre>";
    return [$msg, ob_get_clean()];
}
function processAction4() {
    $emails = array_map('strtolower', chargerEmails(getEmailsFile()));
    $byDomain = [];
    foreach ($emails as $email) {
        if (verifierEmail($email)) {
            $byDomain[substr(strrchr($email, '@'), 1)][] = $email;
        }
    }
    $files = [];
    foreach ($byDomain as $domain => $vals) {
        $fname = "emails_" . str_replace('.', '_', $domain) . ".txt";
        enregistrerEmails(filePath($fname), $vals);
        $files[] = $fname;
    }
    $msg = "Emails séparés par domaine: " . implode(", ", $files);
    ob_start();
    foreach ($files as $f) {
        $fp = filePath($f);
        if (file_exists($fp)) echo "<h4>Contenu de $f :</h4><pre>" . file_get_contents($fp) . "</pre>";
    }
    return [$msg, ob_get_clean()];
}
function processAction5() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nouveau_email'])) {
        $n = trim($_POST['nouveau_email']);
        $emails = array_map('strtolower', chargerEmails(getEmailsFile()));
        if (!verifierEmail($n)) $msg = "L'adresse email n'est pas valide.";
        elseif (in_array(strtolower($n), $emails)) $msg = "Cette adresse email existe déjà.";
        else { file_put_contents(getEmailsFile(), strtolower($n) . "\n", FILE_APPEND); $msg = "Adresse email ajoutée."; }
        ob_start();
        echo "<h4>Contenu mis à jour de " . getEmailsFile() . " :</h4><pre>" . file_get_contents(getEmailsFile()) . "</pre>";
        return [$msg, ob_get_clean()];
    } else {
        return ["", displayCurrentEmails()];
    }
}
function processAction6() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['emails_file'])) {
        if ($_FILES['emails_file']['error'] === 0) {
            $orig = basename($_FILES["emails_file"]["name"]);
            $base = preg_replace('/[^a-zA-Z0-9._-]/', '', pathinfo($orig, PATHINFO_FILENAME));
            $targetDir = UPLOADS_PARENT_DIR . '/' . $base;
            is_dir($targetDir) or mkdir($targetDir, 0755, true);
            if (strtolower(pathinfo($orig, PATHINFO_EXTENSION)) !== 'txt') {
                return ["❌ Error: Only .txt files allowed.", ""];
            }
            $dest = $targetDir . '/' . $orig;
            if (move_uploaded_file($_FILES['emails_file']['tmp_name'], $dest)) {
                copy($dest, $targetDir . '/Emails.txt');
                $_GET['dir'] = $base;
                $msg = "✅ File uploaded and processed as Emails.txt in '$targetDir'.";
            } else { $msg = "❌ Error: Failed to move uploaded file."; }
        } else { $msg = "❌ Error: Upload error occurred."; }
        ob_start();
        echo "<h4>Contenu mis à jour de " . getEmailsFile() . " :</h4><pre>" . (file_exists(getEmailsFile()) ? file_get_contents(getEmailsFile()) : "") . "</pre>";
        return [$msg, ob_get_clean()];
    } else {
        ob_start(); ?>
        <h3>Upload Emails File</h3>
        <form action="?action=6" method="post" enctype="multipart/form-data" class="mx-auto" style="max-width:400px;">
            <div class="mb-3">
                <label for="emails_file" class="form-label">Select Emails.txt File:</label>
                <input type="file" name="emails_file" id="emails_file" class="form-control" accept=".txt" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Upload File</button>
        </form>
        <?php return ["", ob_get_clean()];
    }
}

// ------------------------------
// Main Execution (Switch block remains unchanged)
// ------------------------------
$action = $_GET['action'] ?? '';
$message = '';
$displayContent = '';

switch ($action) {
    case '1':
        list($message, $displayContent) = processAction1();
        break;
    case '2':
        list($message, $displayContent) = processAction2();
        break;
    case '3':
        list($message, $displayContent) = processAction3();
        break;
    case '4':
        list($message, $displayContent) = processAction4();
        break;
    case '5':
        list($message, $displayContent) = processAction5();
        break;
    case '6':
        list($message, $displayContent) = processAction6();
        break;
    default:
        $displayContent = displayCurrentEmails();
        break;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Emails</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-5">
    <h1 class="text-center">Gestion des Emails</h1>
    
    <nav class="my-4 text-center">
        <a href="?action=1<?= isset($_GET['dir']) ? '&dir=' . urlencode($_GET['dir']) : '' ?>" class="btn btn-primary mx-1">1 - Nettoyer la liste</a>
        <a href="?action=2<?= isset($_GET['dir']) ? '&dir=' . urlencode($_GET['dir']) : '' ?>" class="btn btn-secondary mx-1">2 - Afficher fréquence</a>
        <a href="?action=3<?= isset($_GET['dir']) ? '&dir=' . urlencode($_GET['dir']) : '' ?>" class="btn btn-success mx-1">3 - Dédoublonner & trier</a>
        <a href="?action=4<?= isset($_GET['dir']) ? '&dir=' . urlencode($_GET['dir']) : '' ?>" class="btn btn-info mx-1">4 - Séparer par domaine</a>
        <a href="?action=5<?= isset($_GET['dir']) ? '&dir=' . urlencode($_GET['dir']) : '' ?>" class="btn btn-warning mx-1">5 - Ajouter une adresse</a>
        <a href="?action=6" class="btn btn-dark mx-1">6 - Upload File</a>
    </nav>
    
    <div class="my-4">
        <?= listDownloadLinks(); ?>
    </div>
    
    <?php if (!empty($message)) : ?>
        <div class="alert alert-info text-center"><?= $message ?></div>
    <?php endif; ?>
    
    <?php if ($action === '5') : ?>
        <h3 class="text-center">Ajouter une adresse email</h3>
        <form method="post" action="?action=5<?= isset($_GET['dir']) ? '&dir=' . urlencode($_GET['dir']) : '' ?>" class="mx-auto" style="max-width:400px;">
            <div class="mb-3">
                <label for="nouveau_email" class="form-label">Adresse email :</label>
                <input type="email" class="form-control" id="nouveau_email" name="nouveau_email" required>
            </div>
            <button type="submit" class="btn btn-warning w-100">Ajouter</button>
        </form>
    <?php endif; ?>
    
    <div class="mt-4"><?= $displayContent ?></div>
    
</body>
</html>
