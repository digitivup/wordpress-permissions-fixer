# 🔒 wordpress-permissions-fixer

An ultra-optimized, memory-safe, and standalone PHP script to safely reset WordPress file and directory permissions to recommended secure defaults, featuring a modern UI dashboard.

---

## 🇺🇸 English Version

A single corrupted or poorly configured permission can compromise your entire WordPress installation or trigger annoying `500 Internal Server Errors`. This standalone utility scans your structure and repairs everything in seconds without overloading your server.

### ✨ Features

- **High Performance:** Inspects current file flags via `fileperms()` before requesting changes, bypassing thousands of redundant I/O disk writes.
- **Memory Protection:** Throttles UI logs automatically during heavily corrupted tree rollbacks to avoid breaking server process RAM limits (`MAX_LOG_ENTRIES`).
- **Shared Hosting Smart Detection:** Identifies system owner boundaries (e.g., standard OVH Cloud Shared behaviors) instead of throwing false errors.
- **Instant Clean Up:** Single-click self-destruction sequence ensures no dangerous automation utilities remain exposed on your server.
- **Modern UI:** A clean, SaaS-style dashboard that replaces old, unreadable text console streams.

### 🚀 How To Use

1. **Download** the `fix_permissions.php` script.
2. *(Optional)* Open the file and edit the `SECRET_KEY` constant to set a permanent password.
3. **Upload** the file directly into your WordPress core root directory (where `wp-config.php` lives) via FTP/SFTP or your hosting panel.
4. **Navigate** to the file using your favorite web browser: `https://example.com/fix_permissions.php`
5. If you didn't define a custom key in step 2, the script will securely generate a temporary link for you. Click **"Launch with Temporary Secure Key"**.
6. Review the live dashboard analysis grid.
7. ⚠️ **CRITICAL:** Click the red **"Clean up and Delete This Script From Server"** button at the bottom once completed.

### 🛠️ Core Defaults Applied
- **Directories:** `755`
- **Standard Files:** `644`
- **`wp-config.php`:** `640` *(Optimized for 99% of modern secure shared/dedicated hostings)*

---

## 🇫🇷 Version Française

Une seule permission corrompue ou mal configurée peut compromettre l'intégralité de votre installation WordPress ou déclencher de fâcheuses erreurs `500 Internal Server Error`. Cet utilitaire autonome analyse votre structure et répare tout en quelques secondes sans surcharger votre serveur.

### ✨ Fonctionnalités

- **Haute Performance :** Inspecte l'état réel via `fileperms()` avant d'appliquer un changement, évitant des milliers d'écritures I/O redondantes sur le disque.
- **Protection de la Mémoire :** Limite automatiquement l'affichage du journal en cas de désynchronisation massive pour ne pas dépasser la mémoire RAM du serveur (`MAX_LOG_ENTRIES`).
- **Détection Intelligente (Mutualisé) :** Identifie les restrictions de propriétaire (ex: comportement classique des architectures OVH Cloud) au lieu de lever de fausses alertes.
- **Auto-Suppression Instantanée :** Un bouton de destruction en un clic garantit qu'aucun outil d'automatisation sensible ne reste accessible.
- **Interface Moderne :** Un tableau de bord élégant style SaaS qui remplace les lignes de console textuelles illisibles.

### 🚀 Comment l'utiliser

1. **Téléchargez** le script `fix_permissions.php`.
2. *(Optionnel)* Ouvrez le fichier et modifiez la constante `SECRET_KEY` pour y définir votre propre clé d'accès.
3. **Téléversez (Upload)** le fichier directement à la racine de votre site WordPress (là où se trouve `wp-config.php`) via FTP/SFTP ou votre panneau d'hébergement.
4. **Accédez** au fichier via votre navigateur : `https://mon-site.fr/fix_permissions.php`
5. Si vous n'avez pas défini de clé personnalisée à l'étape 2, le script génère un lien sécurisé temporaire. Cliquez simplement sur **"Launch with Temporary Secure Key"**.
6. Observez le tableau de bord et le rapport d'activité.
7. ⚠️ **CRITIQUE :** Cliquez sur le bouton rouge **"Supprimer définitivement ce script du serveur"** tout en bas dès que l'opération est terminée.

### 🛠️ Permissions Appliquées par Défaut
- **Dossiers :** `755`
- **Fichiers standards :** `644`
- **`wp-config.php` :** `640` *(Optimisé pour être sécurisé et compatible avec 99% des hébergeurs)*
