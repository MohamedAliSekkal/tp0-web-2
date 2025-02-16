<?php
// ------------------------------
// Configuration & Constants
// ------------------------------
const UPLOADS_PARENT_DIR = 'uploads';  // Parent directory for all uploads

// When no specific directory is set (via ?dir=...), use a default folder.
function getBaseDir() {
    if (isset($_GET['dir']) && !empty($_GET['dir'])) {
        // Use basename() to prevent directory traversal.
        $dir = basename($_GET['dir']);
    } else {
        $dir = 'default';
    }
    $baseDir = UPLOADS_PARENT_DIR . '/' . $dir;
    // Ensure the directory exists.
    if (!is_dir($baseDir)) {
        if (!mkdir($baseDir, 0755, true)) {
            error_log("[".date('Y-m-d H:i:s')."] Failed to create default base directory: $baseDir");
        }
    }
    return $baseDir;
}

// File path getters (all relative to the base directory)
function getEmailsFile() {
    return getBaseDir() . '/Emails.txt';
}

function getAdressesNonValideFile() {
    return getBaseDir() . '/adressesNonValides.txt';
}

function getEmailsTriesFile() {
    return getBaseDir() . '/EmailsT.txt';
}

// ------------------------------
// Core Functions
// ------------------------------

/* ----------------------------------------------------------
   verifierEmail : Vérifie si une adresse email est valide.
---------------------------------------------------------- */
function verifierEmail($adresse) {
    return filter_var($adresse, FILTER_VALIDATE_EMAIL);
}

/* ----------------------------------------------------------
   chargerEmails : Lit un fichier et retourne un tableau d'emails.
---------------------------------------------------------- */
function chargerEmails($fichier) {
    return file_exists($fichier)
        ? array_map('trim', file($fichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
        : [];
}

/* ----------------------------------------------------------
   enregistrerEmails : Sauvegarde un tableau d'emails dans un fichier.
---------------------------------------------------------- */
function enregistrerEmails($fichier, $emails) {
    if (file_put_contents($fichier, implode("\n", $emails) . "\n") === false) {
        error_log("[".date('Y-m-d H:i:s')."] Failed to write to file: $fichier");
    }
}

/* ----------------------------------------------------------
   displayCurrentEmails : Affiche le contenu actuel d'Emails.txt.
---------------------------------------------------------- */
function displayCurrentEmails() {
    ob_start();
    echo "<h4>Contenu actuel de " . getEmailsFile() . " :</h4>";
    if (file_exists(getEmailsFile())) {
        echo "<pre>" . file_get_contents(getEmailsFile()) . "</pre>";
    } else {
        echo "<pre>Aucun email enregistré.</pre>";
    }
    return ob_get_clean();
}

// ------------------------------
// Action Functions (1-6)
// ------------------------------

/* ----------------------------------------------------------
   Action 1 : Nettoyer la liste (séparer emails valides et non valides)
---------------------------------------------------------- */
function processAction1() {
    $emails = chargerEmails(getEmailsFile());
    $validEmails = [];
    $invalidEmails = [];
    
    foreach ($emails as $email) {
        if (verifierEmail($email)) {
            $validEmails[] = strtolower($email);
        } else {
            $invalidEmails[] = $email;
        }
    }
    enregistrerEmails(getEmailsFile(), $validEmails);
    if (count($invalidEmails) > 0) {
        enregistrerEmails(getAdressesNonValideFile(), $invalidEmails);
    }
    
    $message = "Nettoyage effectué : " . count($invalidEmails) . " adresses non valides supprimées (enregistrées dans '" . getAdressesNonValideFile() . "') et " . count($validEmails) . " adresses valides conservées dans '" . getEmailsFile() . "'.";
    
    ob_start();
    echo "<h4>Contenu de " . getEmailsFile() . " (Emails valides) :</h4>";
    echo "<pre>" . file_get_contents(getEmailsFile()) . "</pre>";
    if (file_exists(getAdressesNonValideFile())) {
        echo "<h4>Contenu de " . getAdressesNonValideFile() . " (Emails non valides) :</h4>";
        echo "<pre>" . file_get_contents(getAdressesNonValideFile()) . "</pre>";
    }
    $displayContent = ob_get_clean();
    
    return [$message, $displayContent];
}

/* ----------------------------------------------------------
   Action 2 : Afficher un tableau contenant les adresses emails et leur fréquence
---------------------------------------------------------- */
function processAction2() {
    $emails = chargerEmails(getEmailsFile());
    $emailsLower = array_map('strtolower', $emails);
    $frequence = array_count_values($emailsLower);

    ob_start();
    ?>
    <h3>Tableau des adresses emails et leur fréquence</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Email</th>
                <th>Fréquence</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($frequence as $email => $count): ?>
            <tr>
                <td><?= $email ?></td>
                <td><?= $count ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    $displayContent = ob_get_clean();
    $message = "";
    return [$message, $displayContent];
}

/* ----------------------------------------------------------
   Action 3 : Supprimer les doublons, trier et enregistrer dans EmailsT.txt
---------------------------------------------------------- */
function processAction3() {
    $emails = chargerEmails(getEmailsFile());
    $emailsLower = array_map('strtolower', $emails);
    $emailsUnique = array_unique($emailsLower);
    sort($emailsUnique);
    enregistrerEmails(getEmailsTriesFile(), $emailsUnique);
    
    $message = "Les emails ont été dédoublonnés et triés. Le résultat a été enregistré dans '" . getEmailsTriesFile() . "'. Nombre d'emails : " . count($emailsUnique) . ".";
    
    ob_start();
    echo "<h4>Contenu de " . getEmailsTriesFile() . " :</h4>";
    echo "<pre>" . file_get_contents(getEmailsTriesFile()) . "</pre>";
    $displayContent = ob_get_clean();
    
    return [$message, $displayContent];
}

/* ----------------------------------------------------------
   Action 4 : Séparer les emails par domaine et les enregistrer dans des fichiers distincts.
---------------------------------------------------------- */
function processAction4() {
    $emails = chargerEmails(getEmailsFile());
    $emailsLower = array_map('strtolower', $emails);
    $emailsParDomaine = [];
    
    foreach ($emailsLower as $email) {
        if (verifierEmail($email)) {
            // Récupération du domaine : tout ce qui suit le dernier '@'
            $domaine = substr(strrchr($email, '@'), 1);
            $emailsParDomaine[$domaine][] = $email;
        }
    }
    
    $filesCreated = [];
    foreach ($emailsParDomaine as $domaine => $emailsDomain) {
        // Pour le nom du fichier, remplacer les points par des underscores
        $filename = "emails_" . str_replace('.', '_', $domaine) . ".txt";
        $filePath = getBaseDir() . '/' . $filename;
        enregistrerEmails($filePath, $emailsDomain);
        $filesCreated[] = $filename;
    }
    
    $message = "Les emails ont été séparés par domaine. Fichiers créés : " . implode(", ", $filesCreated) . ".";
    
    ob_start();
    foreach ($filesCreated as $file) {
        $filePath = getBaseDir() . '/' . $file;
        if (file_exists($filePath)) {
            echo "<h4>Contenu de $file :</h4>";
            echo "<pre>" . file_get_contents($filePath) . "</pre>";
        }
    }
    $displayContent = ob_get_clean();
    
    return [$message, $displayContent];
}

/* ----------------------------------------------------------
   Action 5 : Ajouter une adresse email via un formulaire
---------------------------------------------------------- */
function processAction5() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nouveau_email'])) {
        $nouveauEmail = trim($_POST['nouveau_email']);
        $emails = chargerEmails(getEmailsFile());
        $emailsLower = array_map('strtolower', $emails);
        if (!verifierEmail($nouveauEmail)) {
            $message = "L'adresse email n'est pas valide.";
        } elseif (in_array(strtolower($nouveauEmail), $emailsLower)) {
            $message = "Cette adresse email existe déjà.";
        } else {
            file_put_contents(getEmailsFile(), strtolower($nouveauEmail) . "\n", FILE_APPEND);
            $message = "Adresse email ajoutée avec succès.";
        }
        ob_start();
        echo "<h4>Contenu mis à jour de " . getEmailsFile() . " :</h4>";
        echo "<pre>" . file_get_contents(getEmailsFile()) . "</pre>";
        $displayContent = ob_get_clean();
    } else {
        $displayContent = displayCurrentEmails();
        $message = "";
    }
    return [$message, $displayContent];
}

/* ----------------------------------------------------------
   Action 6 : Upload un fichier, créer une organisation dynamique des fichiers,
              et rediriger tous les traitements dans le dossier correspondant.
---------------------------------------------------------- */
function processAction6() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['emails_file'])) {
        // Check for file upload errors.
        if ($_FILES['emails_file']['error'] === 0) {
            // Extract the original filename.
            $originalFilename = basename($_FILES["emails_file"]["name"]);
            // Use pathinfo to extract the base name (without extension)
            $baseName = pathinfo($originalFilename, PATHINFO_FILENAME);
            // Sanitize the base name (allow letters, numbers, dot, underscore, hyphen)
            $baseName = preg_replace('/[^a-zA-Z0-9._-]/', '', $baseName);
            
            // Define the target directory (inside UPLOADS_PARENT_DIR) using the extracted base name.
            $targetDir = UPLOADS_PARENT_DIR . '/' . $baseName;
            // If the directory already exists, you can either use it or append a unique suffix.
            if (!is_dir($targetDir)) {
                if (!mkdir($targetDir, 0755, true)) {
                    error_log("[".date('Y-m-d H:i:s')."] Failed to create directory: $targetDir");
                    $message = "❌ Error: Could not create target directory.";
                    return [$message, ""];
                }
            }
            // Validate file extension (only allow .txt files)
            $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
            if (strtolower($extension) !== 'txt') {
                $message = "❌ Error: Only .txt files are allowed.";
                return [$message, ""];
            }
            // Define the full target file path.
            $uploadFile = $targetDir . '/' . $originalFilename;
            if (move_uploaded_file($_FILES['emails_file']['tmp_name'], $uploadFile)) {
                // Copy the uploaded file as Emails.txt in the target directory.
                if (!copy($uploadFile, $targetDir . '/Emails.txt')) {
                    error_log("[".date('Y-m-d H:i:s')."] Failed to copy $uploadFile to Emails.txt in $targetDir");
                    $message = "❌ Error: Failed to process the uploaded file.";
                    return [$message, ""];
                }
                // Set the GET parameter 'dir' so that subsequent processing uses this directory.
                $_GET['dir'] = $baseName;
                $message = "✅ File uploaded successfully. Processing as Emails.txt in directory '$targetDir'.";
            } else {
                $message = "❌ Error: Failed to move the uploaded file.";
            }
        } else {
            $message = "❌ Error: No file uploaded or an upload error occurred.";
        }
        ob_start();
        echo "<h4>Contenu mis à jour de " . getEmailsFile() . " :</h4>";
        if (file_exists(getEmailsFile())) {
            echo "<pre>" . file_get_contents(getEmailsFile()) . "</pre>";
        }
        $displayContent = ob_get_clean();
        return [$message, $displayContent];
    } else {
        // Display the file upload form.
        ob_start();
        ?>
        <h3>Upload Emails File</h3>
        <form action="?action=6" method="post" enctype="multipart/form-data" class="mx-auto" style="max-width: 400px;">
            <div class="mb-3">
                <label for="emails_file" class="form-label">Select Emails.txt File:</label>
                <input type="file" name="emails_file" id="emails_file" class="form-control" accept=".txt" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Upload File</button>
        </form>
        <?php
        $displayContent = ob_get_clean();
        $message = "";
        return [$message, $displayContent];
    }
}

// ------------------------------
// Main Execution: Determine the Action
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
        // Aucune action spécifiée, affiche le contenu actuel.
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
    
    <?php if (!empty($message)) : ?>
        <div class="alert alert-info text-center">
            <?= $message ?>
        </div>
    <?php endif; ?>
    
    <?php if ($action === '5') : ?>
        <h3 class="text-center">Ajouter une adresse email</h3>
        <form method="post" action="?action=5<?= isset($_GET['dir']) ? '&dir=' . urlencode($_GET['dir']) : '' ?>" class="mx-auto" style="max-width: 400px;">
            <div class="mb-3">
                <label for="nouveau_email" class="form-label">Adresse email :</label>
                <input type="email" class="form-control" id="nouveau_email" name="nouveau_email" required>
            </div>
            <button type="submit" class="btn btn-warning w-100">Ajouter</button>
        </form>
    <?php endif; ?>
    
    <div class="mt-4">
        <?= $displayContent ?>
    </div>
    
</body>
</html>
