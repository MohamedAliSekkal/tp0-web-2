<?php
// Définition des fichiers utilisés
const EMAILS_FILE = 'Emails.txt';
const ADRESSES_NON_VALIDE_FILE = 'adressesNonValides.txt';
const EMAILS_TRIES_FILE = 'EmailsT.txt';

/* ----------------------------------------------------------
   Fonction verifierEmail : Vérifie si une adresse email est valide.
   ---------------------------------------------------------- */
function verifierEmail($adresse) {
    return filter_var($adresse, FILTER_VALIDATE_EMAIL);
}

/* ----------------------------------------------------------
   Fonction chargerEmails :
   Lit un fichier et retourne un tableau d'emails.
   ---------------------------------------------------------- */
function chargerEmails($fichier) {
    return file_exists($fichier)
        ? array_map('trim', file($fichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
        : [];
}

/* ----------------------------------------------------------
   Fonction enregistrerEmails :
   Sauvegarde un tableau d'emails dans un fichier.
   ---------------------------------------------------------- */
function enregistrerEmails($fichier, $emails) {
    file_put_contents($fichier, implode("\n", $emails) . "\n");
}


$action = $_GET['action'] ?? '';
$message = '';
$displayContent = '';

if ($action === '1') {
    // Action 1 : Nettoyer la liste
    $emails = chargerEmails(EMAILS_FILE);
    $validEmails = [];
    $invalidEmails = [];
    foreach ($emails as $email) {
        if (verifierEmail($email)) {
            $validEmails[] = strtolower($email);
        } else {
            $invalidEmails[] = $email;
        }
    }
    enregistrerEmails(EMAILS_FILE, $validEmails);
    if (count($invalidEmails) > 0) {
        enregistrerEmails(ADRESSES_NON_VALIDE_FILE, $invalidEmails);
    }
    $message = "Nettoyage effectué : " . count($invalidEmails) . " adresses non valides supprimées (enregistrées dans '" . ADRESSES_NON_VALIDE_FILE . "') et " . count($validEmails) . " adresses valides conservées dans '" . EMAILS_FILE . "'.";
    
    ob_start();
    echo "<h4>Contenu de " . EMAILS_FILE . " (Emails valides) :</h4>";
    echo "<pre>" . file_get_contents(EMAILS_FILE). "</pre>";
    if (file_exists(ADRESSES_NON_VALIDE_FILE)) {
        echo "<h4>Contenu de " . ADRESSES_NON_VALIDE_FILE . " (Emails non valides) :</h4>";
        echo "<pre>" . file_get_contents(ADRESSES_NON_VALIDE_FILE). "</pre>";
    }
    $displayContent = ob_get_clean();

} elseif ($action === '2') {
    // Action 2 : Afficher un tableau contenant les adresses emails et leur fréquence
    $emails = chargerEmails(EMAILS_FILE);
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

} elseif ($action === '3') {
    // Action 3 : Supprimer les doublons, trier et enregistrer dans EmailsT.txt
    $emails = chargerEmails(EMAILS_FILE);
    $emailsLower = array_map('strtolower', $emails);
    $emailsUnique = array_unique($emailsLower);
    sort($emailsUnique);
    enregistrerEmails(EMAILS_TRIES_FILE, $emailsUnique);
    $message = "Les emails ont été dédoublonnés et triés. Le résultat a été enregistré dans '" . EMAILS_TRIES_FILE . "'. Nombre d'emails : " . count($emailsUnique) . ".";
    
    if (file_exists(EMAILS_TRIES_FILE)) {
        ob_start();
        echo "<h4>Contenu de " . EMAILS_TRIES_FILE . " :</h4>";
        echo "<pre>" . file_get_contents(EMAILS_TRIES_FILE) . "</pre>";
        $displayContent = ob_get_clean();
    }

} elseif ($action === '4') {
    // Action 4 : Séparer les emails par domaine et les enregistrer dans des fichiers distincts.
    $emails = chargerEmails(EMAILS_FILE);
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
        enregistrerEmails($filename, $emailsDomain);
        $filesCreated[] = $filename;
    }
    $message = "Les emails ont été séparés par domaine. Fichiers créés : " . implode(", ", $filesCreated) . ".";
    
    if (!empty($filesCreated)) {
        ob_start();
        foreach ($filesCreated as $file) {
            if (file_exists($file)) {
                echo "<h4>Contenu de $file :</h4>";
                echo "<pre>" . file_get_contents($file) . "</pre>";
            }
        }
        $displayContent = ob_get_clean();
    }

} elseif ($action === '5') {
    // Action 5 : Ajouter une adresse email via un formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nouveau_email'])) {
        $nouveauEmail = trim($_POST['nouveau_email']);
        $emails = chargerEmails(EMAILS_FILE);
        $emailsLower = array_map('strtolower', $emails);
        if (!verifierEmail($nouveauEmail)) {
            $message = "AAAAAAAAAA L'adresse email n'est pas valide.";
        } elseif (in_array(strtolower($nouveauEmail), $emailsLower)) {
            $message = "oups Cette adresse email existe déjà.";
        } else {
            file_put_contents(EMAILS_FILE, strtolower($nouveauEmail) . "\n", FILE_APPEND);
            $message = "wiii Adresse email ajoutée avec succès.";
        }
        
        ob_start();
        echo "<h4>Contenu mis à jour de " . EMAILS_FILE . " :</h4>";
        echo "<pre>" . file_get_contents(EMAILS_FILE) . "</pre>";
        $displayContent = ob_get_clean();
    } else {
        ob_start();
        echo "<h4>Contenu actuel de " . EMAILS_FILE . " :</h4>";
        if (file_exists(EMAILS_FILE)) {
            echo "<pre>" . file_get_contents(EMAILS_FILE) . "</pre>";
        } else {
            echo "<pre>Aucun email enregistré.</pre>";
        }
        $displayContent = ob_get_clean();
    }

} elseif ($action === '6') {
    // Action 6 : Upload a file (emails.txt) via an HTML form, process it, and store it on the server.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['emails_file'])) {
        // Check for upload errors
        if ($_FILES['emails_file']['error'] === 0) {
            $uploadDir = "uploads/";
            // Ensure the upload directory exists
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            // Create a secure file path
            $filename = basename($_FILES["emails_file"]["name"]);
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            if (strtolower($extension) !== 'txt') {
                $message = "❌ Error: Only .txt files are allowed.";
            } else {
                $uploadFile = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['emails_file']['tmp_name'], $uploadFile)) {
                    // Optionally, copy the uploaded file to EMAILS_FILE to process it with your functions
                    copy($uploadFile, EMAILS_FILE);
                    $message = "✅ File uploaded successfully and processed as Emails.txt.";
                } else {
                    $message = "❌ Error: Failed to move the uploaded file.";
                }
            }
        } else {
            $message = "❌ Error: No file uploaded or an upload error occurred.";
        }
        ob_start();
        echo "<h4>Uploaded and Processed Emails (Emails.txt):</h4>";
        if (file_exists(EMAILS_FILE)) {
            echo "<pre>" . htmlspecialchars(file_get_contents(EMAILS_FILE)) . "</pre>";
        }
        $displayContent = ob_get_clean();
    } else {
        // Display the file upload form
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
    }
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
        <a href="?action=1" class="btn btn-primary mx-1">1 - Nettoyer la liste</a>
        <a href="?action=2" class="btn btn-secondary mx-1">2 - Afficher fréquence</a>
        <a href="?action=3" class="btn btn-success mx-1">3 - Dédoublonner & trier</a>
        <a href="?action=4" class="btn btn-info mx-1">4 - Séparer par domaine</a>
        <a href="?action=5" class="btn btn-warning mx-1">5 - Ajouter une adresse</a>
        <a href="?action=6" class="btn btn-dark mx-1">6 - Upload File</a>
    </nav>
    
    <?php if (!empty($message)) : ?>
        <div class="alert alert-info text-center">
            <?= $message ?>
        </div>
    <?php endif; ?>
    
    <?php 
    if ($action === '5') : ?>
        <h3 class="text-center">Ajouter une adresse email</h3>
        <form method="post" action="?action=5" class="mx-auto" style="max-width: 400px;">
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
