<?php require_once __DIR__ . "/_track.php"; ?>
<?php require_once __DIR__ . "/_track.php"; ?>
<?php
// --- index.php ---

// --- REDIRECTS (Keep these as Nginx is the better place, but good fallback) ---
if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === '212.47.70.218') {
    $redirect_url = 'https://sortia.ai' . ($_SERVER['REQUEST_URI'] ?? '');
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $redirect_url);
    exit;
}
if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'sortia.ai' && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off')) {
    $redirect_url = 'https://sortia.ai' . ($_SERVER['REQUEST_URI'] ?? '');
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $redirect_url);
    exit;
}

error_log("--- index.php started for URI: " . ($_SERVER['REQUEST_URI'] ?? 'Unknown URI') . " ---");

// --- Language Configuration ---
$available_languages = ['en', 'de'];
$default_language = 'en';

// --- Language Detection ---
$uri_path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
$path_parts = explode('/', $uri_path);
$requested_lang_segment = $path_parts[0] ?? null; // Use null if not present

$current_lang = null;
$page_identifier = ''; // The part of the path after the language code

if ($requested_lang_segment && in_array($requested_lang_segment, $available_languages)) {
    $current_lang = $requested_lang_segment;
    // The rest of the path parts form the page identifier
    $page_identifier_parts = array_slice($path_parts, 1);
    // Handle trailing slashes by filtering out empty segments that might result from it
    $page_identifier_parts = array_filter($page_identifier_parts, function($part) {
        return $part !== '';
    });
    $page_identifier = implode('/', $page_identifier_parts);
} else {
    // If the first segment is not a language, or no segment, it's potentially a 404
    // Nginx redirects "/" to "/en/", so index.php should ideally always be called with a lang segment.
    // If it's reached without a valid language segment (and it's not an asset like /images/logo.png),
    // it's an invalid path structure for this application.
    // Nginx's try_files $uri $uri/ /index.php will also pass asset requests if they don't exist.
    // We should be careful not to 404 valid asset paths if they are mistakenly routed here.
    // However, for typical page content, no valid language segment means 404.

    // Check if it's an asset request pattern (simple check, might need refinement)
    if (preg_match('/\.(jpeg|jpg|gif|png|css|js|ico|xml|webmanifest|json)$/i', $uri_path)) {
        // This is likely an asset Nginx couldn't find.
        // Nginx will serve its own 404 if try_files fails and this script is the fallback.
        // We can send a 404 here too, to be explicit for assets not found by Nginx.
        error_log("PHP 404 Trigger: Asset-like path '{$uri_path}' not found by Nginx and routed to index.php.");
        http_response_code(404);
        // You might want to include your 404.html content here or exit.
        // For now, just exiting after setting the code is fine; Nginx will handle the error page.
        exit;
    } else if (!empty($uri_path)) { // If it's not empty and not a recognized language path
        error_log("PHP 404 Trigger: Path '{$uri_path}' does not start with a valid language segment.");
        http_response_code(404);
        // Consider loading and displaying your 404.html content here directly for better UX
        // include '404.html'; // Make sure paths are correct if you do this
        exit;
    }
    // If $uri_path is empty, Nginx should have redirected to /en/ or /de/.
    // If it still reaches here, set a default language to avoid errors, but this state is unusual.
    $current_lang = $default_language;
    $page_identifier = ''; // Treat as root of default language
}


// --- Content Routing / Page Validation ---
// Define what are considered valid "pages" or "content identifiers" after the language segment.
// For this example, let's assume only the root of the language is valid (e.g., /en/ or /de/).
// Any other $page_identifier means it's a 404.
// You'll need to expand this logic if you have actual sub-pages like /en/about, /de/services etc.

$valid_page_identifiers = [
    '' // Represents the root of the language, e.g., /en/ or /de/
    // Add other valid page identifiers here, e.g., 'contact', 'about-us', 'products/item1'
    // 'cxxycxycyx' is NOT in this list.
];

// The issue you described for /de/cxxycxycyx/ (shows ok) vs /de/cxxycxycyx (shows error)
// My `array_filter` above for $page_identifier_parts and `implode` should normalize
// `cxxycxycyx/` to `cxxycxycyx`. So $page_identifier should be the same.
// The key is this check:
if (!in_array($page_identifier, $valid_page_identifiers)) {
    error_log("PHP 404 Trigger: Page identifier '{$page_identifier}' for language '{$current_lang}' is not a valid page.");
    http_response_code(404);
    // Display your 404.html content (more robust than Nginx's error_page for PHP-driven 404s)
    // Ensure the path to 404.html is correct relative to index.php
    if (file_exists(__DIR__ . '/404.html')) {
        readfile(__DIR__ . '/404.html');
    } else {
        echo "<h1>404 Not Found</h1><p>The page you requested could not be found.</p>"; // Basic fallback
    }
    exit;
}

// If we've reached here, $current_lang is set and $page_identifier is considered valid (e.g., empty for main lang page)

// --- Load Language File ---
$lang_file = __DIR__ . '/lang_' . $current_lang . '.json';
// ... (rest of your language loading logic, unchanged)
$translations = [];
if (file_exists($lang_file)) {
    $translations_json = file_get_contents($lang_file);
    if ($translations_json === false) {
        error_log("Error reading language file: " . $lang_file);
    } else {
        $translations = json_decode($translations_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Error decoding JSON file: " . $lang_file . " - " . json_last_error_msg());
            $translations = [];
        }
    }
} else {
    error_log("Missing language file: " . $lang_file . " (current_lang: {$current_lang}, requested_lang_segment: {$requested_lang_segment}, uri_path: {$uri_path})");
    if ($current_lang !== $default_language) {
        $default_lang_file = __DIR__ . '/lang_' . $default_language . '.json';
        if (file_exists($default_lang_file)) {
             $translations_json = file_get_contents($default_lang_file);
             if ($translations_json !== false) {
                $translations = json_decode($translations_json, true) ?: [];
                error_log("Loaded default language '{$default_language}' as fallback.");
             } else { error_log("Error reading default language file: " . $default_lang_file); }
        } else { error_log("Default language file also missing: " . $default_lang_file); }
    }
}


// ... (rest of your t() function, $base_url, DB connection, logging, and HTML output, unchanged)
// Make sure $base_url, $en_url, $de_url correctly use the $current_lang
function t($key) {
    global $translations, $current_lang, $default_language; // Added default_language
    if (isset($translations[$key])) {
        return htmlspecialchars((string)$translations[$key], ENT_QUOTES, 'UTF-8');
    } else {
        // Fallback to default language if key not found in current language
        if ($current_lang !== $default_language) {
            $default_lang_file = __DIR__ . '/lang_' . $default_language . '.json';
            if (file_exists($default_lang_file)) {
                $default_translations_json = file_get_contents($default_lang_file);
                if ($default_translations_json !== false) {
                    $default_translations = json_decode($default_translations_json, true);
                    if (isset($default_translations[$key])) {
                        error_log("Missing translation key '{$key}' for language '{$current_lang}', used default '{$default_language}'.");
                        return htmlspecialchars((string)$default_translations[$key], ENT_QUOTES, 'UTF-8');
                    }
                }
            }
        }
        error_log("Missing translation key '{$key}' for language '{$current_lang}' (and default).");
        return htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8');
    }
}

// Generate Base URL
$base_url = '/' . ($current_lang ?: $default_language) . '/';
$en_url = '/en/';
$de_url = '/de/';

// Database Connection & Visitor Logging (keep as is)
# require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
// ... (your DB and logging code) ...
$pdo = null;
$db_connection_error = false;

try {
    if (class_exists('Dotenv\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
        $dotenv->load();
    } else { error_log('Dotenv class not found.'); }

    $db_host = $_ENV['DB_HOST'] ?? getenv('DB_HOST');
    $db_name = $_ENV['DB_NAME'] ?? getenv('DB_NAME');
    $db_user = $_ENV['DB_USER'] ?? getenv('DB_USER');
    $db_pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS');

    if ($db_host && $db_name && $db_user && $db_pass) {
        $dsn = "mysql:host=" . $db_host . ";dbname=" . $db_name . ";charset=utf8mb4";
        $options = [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, ];
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    } else { error_log("Database credentials not fully set."); $db_connection_error = true; }
} catch (Dotenv\Exception\InvalidPathException $e) { error_log('Dotenv: .env file not found or path is invalid. ' . $e->getMessage()); $db_connection_error = true;
} catch (PDOException $e) { error_log("Database Connection Error in index.php: " . $e->getMessage()); $pdo = null; $db_connection_error = true;
} catch (Exception $e) { error_log('General setup error in index.php (related to .env or DB): ' . $e->getMessage()); $db_connection_error = true; }


// --- Log Visitor Data ---
if ($pdo) {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $referrer = $_SERVER['HTTP_REFERER'] ?? null;
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? "https" : "http";
        $requested_url = $scheme . "://" . ($_SERVER['HTTP_HOST'] ?? 'unknown.host') . ($_SERVER['REQUEST_URI'] ?? '/');
        $language_logged = $current_lang;

        $sql = "INSERT INTO page_visits (ip_address, user_agent, referrer, requested_url, language, visit_time) VALUES (:ip, :ua, :ref, :url, :lang, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':ip', $ip_address, PDO::PARAM_STR); $stmt->bindParam(':ua', $user_agent, PDO::PARAM_STR); $stmt->bindParam(':ref', $referrer, PDO::PARAM_STR); $stmt->bindParam(':url', $requested_url, PDO::PARAM_STR); $stmt->bindParam(':lang', $language_logged, PDO::PARAM_STR);
        $stmt->execute();
    } catch (PDOException $e) { error_log("Error logging page visit (PDOException): " . $e->getMessage());
    } catch (Exception $ex) { error_log("Error logging page visit (General Exception): " . $ex->getMessage()); }
} elseif ($db_connection_error) { error_log("Visitor logging skipped due to database connection error.");
} else { error_log("Visitor logging skipped: DB credentials not fully set or .env not loaded."); }

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($current_lang ?? $default_language, ENT_QUOTES, 'UTF-8') ?>"> <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('pageTitle') ?></title>

    <meta name="description" content="<?= t('metaDescription') ?>">
    <link rel="canonical" href="https://www.sortia.ai<?= htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8') ?>">
    
    <link rel="apple-touch-icon" sizes="180x180" href="/images/ico/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/ico/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/images/ico/favicon-16x16.png">
    <link rel="manifest" href="/images/ico/site.webmanifest">
    <link rel="icon" href="/images/ico/favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="/images/ico/favicon.ico" type="image/x-icon">
    <link rel="sitemap" type="application/xml" title="Sitemap" href="/sitemap.xml">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://www.sortia.ai<?= htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:title" content="<?= t('ogTitle') ?>">
    <meta property="og:description" content="<?= t('ogDescription') ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://www.sortia.ai<?= htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:title" content="<?= t('twitterTitle') ?>">
    <meta name="twitter:description" content="<?= t('twitterDescription') ?>">

    <link rel="alternate" hreflang="en" href="https://www.sortia.ai<?= htmlspecialchars($en_url, ENT_QUOTES, 'UTF-8') ?>" />
    <link rel="alternate" hreflang="de" href="https://www.sortia.ai<?= htmlspecialchars($de_url, ENT_QUOTES, 'UTF-8') ?>" />
    <link rel="alternate" hreflang="x-default" href="https://www.sortia.ai<?= htmlspecialchars($en_url, ENT_QUOTES, 'UTF-8') ?>" />

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        html { scroll-padding-top: 3.5rem; scroll-behavior: smooth; }
        body { font-family: 'Inter', sans-serif; background-color: #111827; color: #f3f4f6; overflow-x: hidden; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in-element { animation: fadeIn 0.8s ease-out forwards; opacity: 0; }
        .problem-card:nth-child(1) { animation-delay: 0.1s; } .problem-card:nth-child(2) { animation-delay: 0.2s; } .problem-card:nth-child(3) { animation-delay: 0.3s; } .problem-card:nth-child(4) { animation-delay: 0.4s; }
        .tech-card { animation: fadeIn 0.5s ease-out forwards; opacity: 0; }
        .cert-card { animation: fadeIn 0.5s ease-out forwards; opacity: 0; }
        .pulse-button { animation: pulse 2s infinite; }
        @keyframes pulse { 0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(96, 165, 250, 0.7); } 70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(96, 165, 250, 0); } 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(96, 165, 250, 0); } }
        .animate-blob { animation: blob 7s infinite; } .animation-delay-2000 { animation-delay: 2s; } .animation-delay-4000 { animation-delay: 4s; }
        @keyframes blob { 0% { transform: translate(0px, 0px) scale(1); } 33% { transform: translate(30px, -50px) scale(1.1); } 66% { transform: translate(-20px, 20px) scale(0.9); } 100% { transform: translate(0px, 0px) scale(1); } }
        .workflow-connector {
            flex-grow: 0;
            min-width: 1rem;
            height: 3px;
            background: linear-gradient(to right, #60a5fa, #a78bfa);
            margin: 0 0.5rem;
            position: relative;
            opacity: 1;
            align-self: center; /* Vertically center connector */
        }
        .workflow-connector::after { content: ''; position: absolute; right: -8px; top: 50%; transform: translateY(-50%) rotate(45deg); width: 14px; height: 14px; border-top: 4px solid #a78bfa; border-right: 4px solid #a78bfa; }
        .workflow-connector-mobile { width: 3px; height: 30px; background: linear-gradient(to bottom, #60a5fa, #a78bfa); margin: 0.75rem 0; position: relative; opacity: 1; }
        .workflow-connector-mobile::after { content: ''; position: absolute; bottom: -8px; left: 50%; transform: translateX(-50%) rotate(45deg); width: 14px; height: 14px; border-bottom: 4px solid #a78bfa; border-right: 4px solid #a78bfa; }
        .reference-logo { display: flex; justify-content: center; align-items: center; padding: 0.5rem; background-color: white; border-radius: 0.5rem; height: 5rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); }
        .reference-logo img { max-height: 100%; max-width: 100%; width: auto; height: auto; object-fit: contain; transition: transform 0.3s ease; }
        .reference-logo:hover img { transform: scale(1.05); }
        .reference-logo.placeholder { background-color: #eeeeee; display: flex; justify-content: center; align-items: center; text-align: center; color: #a0aec0; font-size: 0.875rem; }
        .reference-logo.placeholder img { display: none; }
        .tech-category { border-left: 3px solid #3b82f6; padding-left: 1.5rem; margin-bottom: 2rem; } .tech-category h3 { color: #60a5fa; }
        .form-input { background-color: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.2); color: #f3f4f6; padding: 0.75rem 1rem; border-radius: 0.5rem; transition: border-color 0.3s ease, box-shadow 0.3s ease; width: 100%; }
        .form-input:focus { outline: none; border-color: #60a5fa; box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.5); }
        .form-textarea { min-height: 120px; } .form-checkbox { accent-color: #60a5fa; }
        .lang-switch a { cursor: pointer; padding: 0.25rem 0.5rem; margin: 0 0.1rem; border-radius: 0.25rem; transition: background-color 0.3s; font-size: 0.875rem; font-weight: 500; text-decoration: none; color: #d1d5db; }
        .lang-switch a.active { background-color: rgba(96, 165, 250, 0.3); color: #93c5fd; }
        .lang-switch a:hover:not(.active) { background-color: rgba(255, 255, 255, 0.1); color: #f9fafb; }
        .image-hover-container { position: relative; display: inline-block; border-radius: 0.5rem; overflow: hidden; border: 4px solid rgba(107, 114, 128, 0.5); box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1); }
        .image-hover-container img { display: block; max-width: 100%; height: auto; object-fit: contain; }
        .image-hover-container .hover-image { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; transition: opacity 0.4s ease-in-out; object-fit: cover; }
        .image-hover-container:hover .hover-image { opacity: 1; }
        .image-hover-container .original-image { transition: opacity 0.4s ease-in-out; }
        .image-hover-container:hover .original-image { opacity: 0.1; }
        /* Prevent image selection/highlighting/dragging */
        img {
            -webkit-user-select: none; /* Safari */
            -moz-user-select: none;    /* Firefox */
            -ms-user-select: none;     /* IE10+/Edge */
            user-select: none;         /* Standard */
            -webkit-user-drag: none;   /* Safari */
            user-drag: none;           /* Standard (less support) */
            -webkit-touch-callout: none; /* iOS Safari */
             pointer-events: none;      /* Also prevent click events directly on image */
        }
        /* Ensure parent links of images remain clickable */
        a > img {
            pointer-events: auto; /* Re-enable pointer events for images inside links */
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 antialiased">

    <header class="bg-gray-900/80 backdrop-blur-sm sticky top-0 z-50 shadow-md">
        <nav class="container mx-auto px-4 sm:px-6 py-2 flex justify-between items-center">
            <a href="<?= htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8') ?>" class="block hover:opacity-80 transition duration-300 logo-link">
                <img src="/images/logos/sortia_logo.png"
                     alt="sortia Logo"
                     class="h-8 md:h-10 w-auto"
                     onerror="this.onerror=null; this.parentElement.innerHTML='<span class=\'text-3xl sm:text-4xl font-bold text-blue-400\'>Sortia</span>';">
                     </a>
            <div class="hidden md:flex items-center space-x-4 lg:space-x-6">
            <a href="#solutions" class="text-gray-300 hover:text-white transition duration-300 text-sm lg:text-base"><?= t('navSolutions') ?></a>
                <a href="#services" class="text-gray-300 hover:text-white transition duration-300 text-sm lg:text-base"><?= t('navServices') ?></a>
                <a href="#references" class="text-gray-300 hover:text-white transition duration-300 text-sm lg:text-base"><?= t('navReferences') ?></a>
                <a href="#get-started" class="bg-[#013A63] hover:bg-blue-600 text-white font-semibold px-4 py-[3px] lg:px-5 lg:py-[3px] rounded-lg transition duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 text-sm lg:text-base"><?= t('navContact') ?></a>
                <div class="lang-switch flex items-center text-gray-300 border-l border-gray-600 ml-4 pl-4">
                    <a href="<?= htmlspecialchars($en_url, ENT_QUOTES, 'UTF-8') ?>" id="lang-en-desktop" class="<?= ($current_lang === 'en' ? 'active' : '') ?>">EN</a>
                    <span class="mx-1">|</span>
                    <a href="<?= htmlspecialchars($de_url, ENT_QUOTES, 'UTF-8') ?>" id="lang-de-desktop" class="<?= ($current_lang === 'de' ? 'active' : '') ?>">DE</a>
                </div>
            </div>
            <button id="mobile-menu-button" class="md:hidden focus:outline-none text-gray-300 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
            </button>
        </nav>
        <div id="mobile-menu" class="md:hidden hidden bg-gray-800/90 backdrop-blur-sm py-4">
            <a href="#solutions" class="block text-gray-300 hover:text-white px-6 py-2 text-center"><?= t('navSolutions') ?></a>
            <a href="#services" class="block text-gray-300 hover:text-white px-6 py-2 text-center"><?= t('navServices') ?></a>
            <a href="#references" class="block text-gray-300 hover:text-white px-6 py-2 text-center"><?= t('navReferences') ?></a>
            <a href="#get-started" class="block bg-[#013A63] hover:bg-blue-600 text-white font-semibold rounded-lg transition duration-300 shadow-lg mx-6 mt-2 py-[3px] text-base text-center"><?= t('navContact') ?></a>
            <div class="lang-switch flex items-center justify-center text-gray-300 mt-4 pt-4 border-t border-gray-700">
                <a href="<?= htmlspecialchars($en_url, ENT_QUOTES, 'UTF-8') ?>" id="lang-en-mobile" class="<?= ($current_lang === 'en' ? 'active' : '') ?>">EN</a>
                <span class="mx-2">|</span>
                <a href="<?= htmlspecialchars($de_url, ENT_QUOTES, 'UTF-8') ?>" id="lang-de-mobile" class="<?= ($current_lang === 'de' ? 'active' : '') ?>">DE</a>
            </div>
        </div>
    </header>

    <section class="relative bg-gradient-to-br from-gray-900 via-gray-800 to-blue-900/50 text-white px-4 sm:px-6 pt-12 md:pt-20 lg:pt-24 pb-6 md:pb-10 lg:pb-12 overflow-hidden">
        <div class="absolute inset-0 opacity-10">
             <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="dotted-pattern" width="16" height="16" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1" fill="rgba(255,255,255,0.5)"/></pattern></defs><rect width="100%" height="100%" fill="url(#dotted-pattern)"/></svg>
        </div>
        <div class="absolute top-1/4 left-1/4 w-48 h-48 md:w-72 md:h-72 bg-blue-500 rounded-full mix-blend-multiply filter blur-xl opacity-30 animate-blob"></div>
        <div class="absolute top-1/2 right-1/4 w-48 h-48 md:w-72 md:h-72 bg-purple-500 rounded-full mix-blend-multiply filter blur-xl opacity-30 animate-blob animation-delay-2000"></div>
        <div class="absolute bottom-1/4 left-1/3 w-48 h-48 md:w-72 md:h-72 bg-pink-500 rounded-full mix-blend-multiply filter blur-xl opacity-30 animate-blob animation-delay-4000"></div>

        <div class="container mx-auto text-center relative z-10">
            <h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold mb-4 leading-tight fade-in-element" style="animation-delay: 0.1s;">
                <?= t('heroHeadline') ?> <span class="text-blue-400"><?= t('heroHeadlineSpan') ?></span>
            </h1>
            <p class="text-base sm:text-lg md:text-xl text-gray-300 mb-6 md:mb-8 max-w-3xl mx-auto fade-in-element" style="animation-delay: 0.3s;">
                <?= t('heroSubheadline') ?>
            </p>

        <div class="mt-16 max-w-7xl w-full mx-auto flex justify-center items-center fade-in-element" style="animation-delay: 0.4s;">
                 <div class="flex flex-col items-center md:flex-row md:items-stretch justify-center md:space-x-1 lg:space-x-2 space-y-4 md:space-y-0">

                    <div class="flex flex-col items-center text-center p-4 md:py-5 md:px-3 border border-blue-400/50 bg-gray-800/30 backdrop-blur-sm rounded-xl shadow-lg w-48 md:w-52 lg:w-56 md:h-52 justify-center brightness-150 transition-transform duration-300 hover:scale-105">
                        <div class="text-3xl md:text-4xl lg:text-5xl text-blue-300 mb-2 md:mb-3"><i class="fas fa-layer-group"></i></div>
                        <span class="text-sm sm:text-base md:text-lg font-semibold text-gray-100 break-words px-1"><?= t('workflowDataInput') ?></span>
                        <span class="whitespace-nowrap text-[0.625rem] sm:text-[0.6875rem] md:text-xs text-gray-400 leading-tight overflow-hidden text-ellipsis block w-full px-1"><?= t('workflowTrigger') ?></span>
                    </div>

                    <div class="workflow-connector hidden md:block"></div>
                    <div class="workflow-connector-mobile md:hidden"></div>

                    <div class="flex flex-col items-center text-center p-4 md:py-5 md:px-3 border border-green-400/50 bg-gray-800/30 backdrop-blur-sm rounded-xl shadow-lg w-48 md:w-52 lg:w-56 md:h-52 justify-center brightness-150 transition-transform duration-300 hover:scale-105">
                        <div class="text-3xl md:text-4xl lg:text-5xl text-green-300 mb-2 md:mb-3"><i class="fas fa-shield-alt"></i></div>
                        <span class="text-sm sm:text-base md:text-lg font-semibold text-gray-100 break-words px-1"><?= t('workflowDataValidation') ?></span>
                        <span class="whitespace-nowrap text-[0.625rem] sm:text-[0.6875rem] md:text-xs text-gray-400 leading-tight overflow-hidden text-ellipsis block w-full px-1"><?= t('workflowQualityCheck') ?></span>
                    </div>

                    <div class="workflow-connector hidden md:block"></div>
                    <div class="workflow-connector-mobile md:hidden"></div>

                    <div class="flex flex-col items-center text-center p-4 md:py-5 md:px-3 border border-purple-400/50 bg-gray-800/30 backdrop-blur-sm rounded-xl shadow-lg w-48 md:w-52 lg:w-56 md:h-52 justify-center brightness-150 transition-transform duration-300 hover:scale-105">
                        <div class="text-3xl md:text-4xl lg:text-5xl text-purple-300 mb-2 md:mb-3"><i class="fas fa-microchip"></i></div>
                        <span class="text-sm sm:text-base md:text-lg font-semibold text-gray-100 break-words px-1"><?= t('workflowAIProcessing') ?></span>
                        <span class="whitespace-nowrap text-[0.625rem] sm:text-[0.6875rem] md:text-xs text-gray-400 leading-tight overflow-hidden text-ellipsis block w-full px-1"><?= t('workflowsortiaCore') ?></span>
                    </div>

                    <div class="workflow-connector hidden md:block"></div>
                    <div class="workflow-connector-mobile md:hidden"></div>

                    <div class="flex flex-col items-center text-center p-4 md:py-5 md:px-3 border border-teal-400/50 bg-gray-800/30 backdrop-blur-sm rounded-xl shadow-lg w-48 md:w-52 lg:w-56 md:h-52 justify-center brightness-150 transition-transform duration-300 hover:scale-105">
                        <div class="text-3xl md:text-4xl lg:text-5xl text-teal-300 mb-2 md:mb-3"><i class="fas fa-chart-line"></i></div>
                        <span class="text-sm sm:text-base md:text-lg font-semibold text-gray-100 break-words px-1"><?= t('workflowResultsOutput') ?></span>
                        <span class="whitespace-nowrap text-[0.625rem] sm:text-[0.6875rem] md:text-xs text-gray-400 leading-tight overflow-hidden text-ellipsis block w-full px-1"><?= t('workflowActionableResults') ?></span>
                    </div>
                </div>
            </div>
            <a href="#learn-more" class="bg-blue-500 hover:bg-blue-600 text-white font-semibold px-6 py-3 md:px-8 md:py-3 rounded-lg transition duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1 text-base md:text-lg fade-in-element pulse-button inline-block mt-10 md:mt-12" style="animation-delay: 0.5s;">
                <?= t('heroLearnMore') ?>
            </a>
        </div>
    </section>

    <section id="flexibility" class="bg-gradient-to-b from-gray-800 to-gray-900 px-4 sm:px-6 pt-8 md:pt-10 lg:pt-12 pb-12 md:pb-16 lg:pb-20">
        <div class="container mx-auto grid grid-cols-1 md:grid-cols-2 gap-10 md:gap-16 items-center">
            <div class="fade-in-element text-center md:text-left">
                <h2 class="text-3xl sm:text-4xl font-bold mb-5 text-blue-300"><?= t('flexibilityTitle') ?></h2>
                <p class="text-lg text-gray-300 mb-6 leading-relaxed">
                    <?= t('flexibilityDesc') ?>
                </p>
                <div class="space-y-3 text-gray-400 mb-8 text-left mx-auto md:mx-0 max-w-lg">
                    <div class="flex items-start space-x-3"> <i class="fas fa-check-circle text-green-400 mt-1 flex-shrink-0"></i> <span><?= t('flexibilityPoint1') ?></span> </div>
                    <div class="flex items-start space-x-3"> <i class="fas fa-check-circle text-green-400 mt-1 flex-shrink-0"></i> <span><?= t('flexibilityPoint2') ?></span> </div>
                     <div class="flex items-start space-x-3"> <i class="fas fa-check-circle text-green-400 mt-1 flex-shrink-0"></i> <span><?= t('flexibilityPoint3') ?></span> </div>
                     <div class="flex items-start space-x-3"> <i class="fas fa-check-circle text-green-400 mt-1 flex-shrink-0"></i> <span><?= t('flexibilityPoint4') ?></span> </div>
                </div>
                <h3 class="text-2xl font-semibold mb-4 text-teal-300"><?= t('flexibilityWhiteLabelTitle') ?></h3>
                <p class="text-lg text-gray-300 leading-relaxed"> <?= t('flexibilityWhiteLabelDesc') ?> </p>
            </div>

            <div class="flex justify-center items-center fade-in-element" style="animation-delay: 0.2s;">
                <div class="image-hover-container">
                     <img src="/images/web/placeholder-problem-solution.jpg" alt="Workflow diagram showing problem, processing, and solution steps" class="original-image" onerror="this.onerror=null; this.src='https://placehold.co/600x400/eeeeee/aaaaaa?text=Workflow+Diagram'; this.alt='Workflow Diagram Placeholder';">
                     <img src="/images/web/image_fa9b4d.jpg" alt="AI Robot Head" class="hover-image" onerror="this.onerror=null; this.src='https://placehold.co/600x400/111827/ffffff?text=AI+Concept'; this.alt='AI Concept Placeholder';">
                </div>
            </div>
            </div>
    </section>

    <section id="learn-more" class="pb-10 md:pb-14 lg:pb-16 bg-gray-900 px-4 sm:px-6">
        <div class="container mx-auto text-center">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold mb-8 md:mb-12 text-blue-300 fade-in-element"> <?= t('actionAITitle') ?> </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 md:gap-8">
                <div class="flex flex-col items-center p-4 md:p-6 bg-gray-800 rounded-lg shadow-lg transform hover:scale-105 transition duration-300 fade-in-element" style="animation-delay: 0.1s;"> <div class="text-4xl md:text-5xl text-blue-400 mb-3 md:mb-4"><i class="fas fa-rocket"></i></div> <h3 class="text-xl md:text-2xl font-semibold mb-2"><?= t('actionAICardActionTitle') ?></h3> <p class="text-sm md:text-base text-gray-400"><?= t('actionAICardActionDesc') ?></p> </div>
                 <div class="flex flex-col items-center p-4 md:p-6 bg-gray-800 rounded-lg shadow-lg transform hover:scale-105 transition duration-300 fade-in-element" style="animation-delay: 0.2s;"> <div class="text-4xl md:text-5xl text-purple-400 mb-3 md:mb-4"><i class="fas fa-brain"></i></div> <h3 class="text-xl md:text-2xl font-semibold mb-2"><?= t('actionAICardAITitle') ?></h3> <p class="text-sm md:text-base text-gray-400"><?= t('actionAICardAIDesc') ?></p> </div>
                 <div class="flex flex-col items-center p-4 md:p-6 bg-gray-800 rounded-lg shadow-lg transform hover:scale-105 transition duration-300 fade-in-element" style="animation-delay: 0.3s;"> <div class="text-4xl md:text-5xl text-teal-400 mb-3 md:mb-4"><i class="fas fa-lightbulb"></i></div> <h3 class="text-xl md:text-2xl font-semibold mb-2"><?= t('actionAICardInsightsTitle') ?></h3> <p class="text-sm md:text-base text-gray-400"><?= t('actionAICardInsightsDesc') ?></p> </div>
                 <div class="flex flex-col items-center p-4 md:p-6 bg-gray-800 rounded-lg shadow-lg transform hover:scale-105 transition duration-300 fade-in-element" style="animation-delay: 0.4s;"> <div class="text-4xl md:text-5xl text-green-400 mb-3 md:mb-4"><i class="fas fa-check-circle"></i></div> <h3 class="text-xl md:text-2xl font-semibold mb-2"><?= t('actionAICardSolutionTitle') ?></h3> <p class="text-sm md:text-base text-gray-400"><?= t('actionAICardSolutionDesc') ?></p> </div>
            </div>
        </div>
    </section>

    <section id="solutions" class="py-10 md:py-14 lg:py-16 bg-gray-900 px-4 sm:px-6">
        <div class="container mx-auto">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-center mb-8 md:mb-12 text-blue-400 fade-in-element"><?= t('problemsTitle') ?></h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 md:gap-8">
                <div class="problem-card bg-gray-800 p-5 md:p-6 rounded-lg shadow-lg border-l-4 border-blue-500 fade-in-element"> <h3 class="text-lg md:text-xl font-semibold mb-2 md:mb-3 text-white"><?= t('problem1Title') ?></h3> <p class="text-sm md:text-base text-gray-400"><?= t('problem1Desc') ?></p> </div>
                 <div class="problem-card bg-gray-800 p-5 md:p-6 rounded-lg shadow-lg border-l-4 border-purple-500 fade-in-element"> <h3 class="text-lg md:text-xl font-semibold mb-2 md:mb-3 text-white"><?= t('problem2Title') ?></h3> <p class="text-sm md:text-base text-gray-400"><?= t('problem2Desc') ?></p> </div>
                 <div class="problem-card bg-gray-800 p-5 md:p-6 rounded-lg shadow-lg border-l-4 border-teal-500 fade-in-element"> <h3 class="text-lg md:text-xl font-semibold mb-2 md:mb-3 text-white"><?= t('problem3Title') ?></h3> <p class="text-sm md:text-base text-gray-400"><?= t('problem3Desc') ?></p> </div>
                 <div class="problem-card bg-gray-800 p-5 md:p-6 rounded-lg shadow-lg border-l-4 border-green-500 fade-in-element"> <h3 class="text-lg md:text-xl font-semibold mb-2 md:mb-3 text-white"><?= t('problem4Title') ?></h3> <p class="text-sm md:text-base text-gray-400"><?= t('problem4Desc') ?></p> </div>
            </div>
        </div>
    </section>

    <section id="services" class="py-10 md:py-14 lg:py-16 bg-gray-800 px-4 sm:px-6 fade-in-element">
        <div class="container mx-auto">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-center mb-12 text-blue-300"><?= t('techTitle') ?></h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="tech-category"> <h3 class="text-xl font-semibold mb-4 flex items-center"><i class="fas fa-cloud mr-3 text-sky-400"></i><?= t('techCatCloud') ?></h3> <ul class="space-y-3 text-sm md:text-base"> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>AWS:</strong> <?= t('techAWS') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Azure:</strong> <?= t('techAzure') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>GCP:</strong> <?= t('techGCP') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>EC2:</strong> <?= t('techEC2') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>S3:</strong> <?= t('techS3') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Lambda:</strong> <?= t('techLambda') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>CloudFormation:</strong> <?= t('techCF') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Azure VMs:</strong> <?= t('techAzureVM') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Azure Blob Storage:</strong> <?= t('techAzureBlob') ?></span></li> </ul> </div>
                <div class="tech-category" style="border-left-color: #a78bfa;"> <h3 class="text-xl font-semibold mb-4 flex items-center" style="color: #a78bfa;"><i class="fas fa-box-open mr-3"></i><?= t('techCatContainer') ?></h3> <ul class="space-y-3 text-sm md:text-base"> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Docker:</strong> <?= t('techDocker') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Kubernetes (K8s):</strong> <?= t('techK8s') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Helm:</strong> <?= t('techHelm') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>EKS:</strong> <?= t('techEKS') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>AKS:</strong> <?= t('techAKS') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>GKE:</strong> <?= t('techGKE') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>OpenShift:</strong> <?= t('techOpenShift') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Istio:</strong> <?= t('techIstio') ?></span></li> </ul> </div>
                <div class="tech-category" style="border-left-color: #34d399;"> <h3 class="text-xl font-semibold mb-4 flex items-center" style="color: #34d399;"><i class="fas fa-database mr-3"></i><?= t('techCatDB') ?></h3> <ul class="space-y-3 text-sm md:text-base"> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>MySQL:</strong> <?= t('techMySQL') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>PostgreSQL:</strong> <?= t('techPostgres') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>MongoDB:</strong> <?= t('techMongo') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Redis:</strong> <?= t('techRedis') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Cassandra:</strong> <?= t('techCassandra') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Elasticsearch:</strong> <?= t('techElastic') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>DynamoDB:</strong> <?= t('techDynamo') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>RDS:</strong> <?= t('techRDS') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>SQL Server:</strong> <?= t('techSQLServer') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Oracle DB:</strong> <?= t('techOracle') ?></span></li> </ul> </div>
                <div class="tech-category" style="border-left-color: #fbbf24;"> <h3 class="text-xl font-semibold mb-4 flex items-center" style="color: #fbbf24;"><i class="fas fa-server mr-3"></i><?= t('techCatOS') ?></h3> <ul class="space-y-3 text-sm md:text-base"> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Linux (Ubuntu, CentOS):</strong> <?= t('techLinux') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Unix:</strong> <?= t('techUnix') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Bash/Shell Scripting:</strong> <?= t('techBash') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Networking (TCP/IP, DNS):</strong> <?= t('techNetwork') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Load Balancers (Nginx, HAProxy):</strong> <?= t('techLB') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Terraform:</strong> <?= t('techTerraform') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Ansible:</strong> <?= t('techAnsible') ?></span></li> </ul> </div>
                <div class="tech-category" style="border-left-color: #f87171;"> <h3 class="text-xl font-semibold mb-4 flex items-center" style="color: #f87171;"><i class="fas fa-cogs mr-3"></i><?= t('techCatAutomation') ?></h3> <ul class="space-y-3 text-sm md:text-base"> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>n8n:</strong> <?= t('techN8n') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Jenkins:</strong> <?= t('techJenkins') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>GitLab CI/CD:</strong> <?= t('techGitlabCI') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>GitHub Actions:</strong> <?= t('techGithubActions') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Zapier:</strong> <?= t('techZapier') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Make (Integromat):</strong> <?= t('techMake') ?></span></li> </ul> </div>
                 <div class="tech-category" style="border-left-color: #22d3ee;"> <h3 class="text-xl font-semibold mb-4 flex items-center" style="color: #22d3ee;"><i class="fas fa-code mr-3"></i><?= t('techCatProg') ?></h3> <ul class="space-y-3 text-sm md:text-base"> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Python:</strong> <?= t('techPython') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>JavaScript:</strong> <?= t('techJS') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Node.js:</strong> <?= t('techNode') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>React:</strong> <?= t('techReact') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Java:</strong> <?= t('techJava') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>Go:</strong> <?= t('techGo') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>PHP:</strong> <?= t('techPHP') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>REST APIs:</strong> <?= t('techREST') ?></span></li> <li class="tech-card flex items-start"><i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i><span><strong>GraphQL:</strong> <?= t('techGraphQL') ?></span></li> </ul> </div>

            </div>
        </div>
    </section>

    <section id="certifications" class="py-10 md:py-14 lg:py-16 bg-gray-900 px-4 sm:px-6 fade-in-element">
        <div class="container mx-auto">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-center mb-12 text-blue-400"><?= t('certsTitle') ?></h2>
            <div class="max-w-3xl mx-auto"> <div class="cert-card bg-gray-800 p-5 md:p-6 rounded-lg shadow-lg border-l-4 border-yellow-500"> <h3 class="text-lg md:text-xl font-semibold mb-2 md:mb-3 text-white flex items-center"> <i class="fas fa-certificate mr-3 text-yellow-400"></i> <?= t('certITIL4Name') ?> </h3> <p class="text-sm md:text-base text-gray-400"><?= t('certITIL4Desc') ?></p> </div> </div>
        </div>
    </section>

    <section id="references" class="py-10 md:py-14 lg:py-16 bg-gray-900 px-4 sm:px-6 text-center fade-in-element">
         <div class="container mx-auto">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold mb-8 md:mb-12 text-blue-400"> <?= t('clientsTitle') ?> </h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-6 md:gap-8 lg:gap-10 items-center">
                <div class="reference-logo"> <img src="/images/logos/image_065dc9.png" alt="Infineon Logo" onerror="this.onerror=null; this.src='https://placehold.co/150x50/eeeeee/aaaaaa?text=Infineon'; this.parentElement.classList.add('placeholder'); this.style.display='none'; this.parentElement.innerHTML += 'Infineon';"> </div>
                <div class="reference-logo"> <img src="/images/logos/image_065e4c.png" alt="Bayerischer Rundfunk Logo" onerror="this.onerror=null; this.src='https://placehold.co/150x50/eeeeee/aaaaaa?text=BR'; this.parentElement.classList.add('placeholder'); this.style.display='none'; this.parentElement.innerHTML += 'BR';"> </div>
                <div class="reference-logo"> <img src="/images/logos/hallhuber.png" alt="Hallhuber Logo" onerror="this.onerror=null; this.src='https://placehold.co/150x50/eeeeee/aaaaaa?text=Hallhuber'; this.parentElement.classList.add('placeholder'); this.style.display='none'; this.parentElement.innerHTML += 'Hallhuber';"> </div>
                <div class="reference-logo"> <img src="/images/logos/gerry_weber.png" alt="Gerry Weber Logo" onerror="this.onerror=null; this.src='https://placehold.co/150x50/eeeeee/aaaaaa?text=Gerry+Weber'; this.parentElement.classList.add('placeholder'); this.style.display='none'; this.parentElement.innerHTML += 'Gerry Weber';"> </div>
                 <div class="reference-logo"> <img src="/images/logos/fidor-bank.png" alt="Fidor Bank Logo" onerror="this.onerror=null; this.src='https://placehold.co/150x50/eeeeee/aaaaaa?text=Fidor+Bank'; this.parentElement.classList.add('placeholder'); this.style.display='none'; this.parentElement.innerHTML += 'Fidor Bank';"> </div>
                 <div class="reference-logo"> <img src="/images/logos/bernstein_bank.png" alt="Bernstein Bank Logo" onerror="this.onerror=null; this.src='https://placehold.co/150x50/eeeeee/aaaaaa?text=Bernstein+Bank'; this.parentElement.classList.add('placeholder'); this.style.display='none'; this.parentElement.innerHTML += 'Bernstein Bank';"> </div>
                <div class="reference-logo"> <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/a/a9/Amazon_logo.svg/200px-Amazon_logo.svg.png" alt="Amazon Logo" onerror="this.onerror=null; this.src='https://placehold.co/150x50/eeeeee/aaaaaa?text=Amazon';"> </div>
                 <div class="reference-logo"> <img src="/images/logos/chip.png" alt="Chip Logo" onerror="this.onerror=null; this.src='https://placehold.co/150x50/eeeeee/aaaaaa?text=Chip'; this.parentElement.classList.add('placeholder'); this.style.display='none'; this.parentElement.innerHTML += 'Chip';"> </div>
                 <div class="reference-logo"> <img src="/images/logos/check24.png" alt="Check24 Logo" onerror="this.onerror=null; this.src='https://placehold.co/150x50/eeeeee/aaaaaa?text=Check24'; this.parentElement.classList.add('placeholder'); this.style.display='none'; this.parentElement.innerHTML += 'Check24';"> </div>
                 <div class="reference-logo"> <img src="/images/logos/ciao.png" alt="Ciao Logo" onerror="this.onerror=null; this.src='https://placehold.co/150x50/eeeeee/aaaaaa?text=Ciao'; this.parentElement.classList.add('placeholder'); this.style.display='none'; this.parentElement.innerHTML += 'Ciao';"> </div>
                <div class="reference-logo"> <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/5/5e/Visa_Inc._logo.svg/150px-Visa_Inc._logo.svg.png" alt="Visa Logo" onerror="this.onerror=null; this.src='https://placehold.co/150x50/eeeeee/aaaaaa?text=Visa';"> </div>
                <div class="reference-logo"> <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/f/fa/American_Express_logo_%282018%29.svg/200px-American_Express_logo_%282018%29.svg.png" alt="American Express Logo" onerror="this.onerror=null; this.src='https://placehold.co/150x50/eeeeee/aaaaaa?text=Amex';"> </div>
                <div class="reference-logo"> <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/b/b5/PayPal.svg/200px-PayPal.svg.png" alt="Paypal Logo" onerror="this.onerror=null; this.src='https://placehold.co/150x50/eeeeee/aaaaaa?text=Paypal';"> </div>
                 <div class="reference-logo"> <img src="/images/logos/payone.png" alt="Payone Logo" onerror="this.onerror=null; this.src='https://placehold.co/150x50/eeeeee/aaaaaa?text=Payone'; this.parentElement.classList.add('placeholder'); this.style.display='none'; this.parentElement.innerHTML += 'Payone';"> </div>
                <div class="reference-logo"> <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/b/b7/MasterCard_Logo.svg/200px-MasterCard_Logo.svg.png" alt="Mastercard Logo" onerror="this.onerror=null; this.src='https://placehold.co/150x50/eeeeee/aaaaaa?text=Mastercard';"> </div>
                 <div class="reference-logo"> <img src="/images/logos/sparkasse.png" alt="Sparkasse Logo" onerror="this.onerror=null; this.src='https://placehold.co/150x50/eeeeee/aaaaaa?text=Sparkasse'; this.parentElement.classList.add('placeholder'); this.style.display='none'; this.parentElement.innerHTML += 'Sparkasse';"> </div>
                 <div class="reference-logo"> <img src="/images/logos/spreadshirt.png" alt="Spreadshirt Logo" onerror="this.onerror=null; this.src='https://placehold.co/150x50/eeeeee/aaaaaa?text=Spreadshirt'; this.parentElement.classList.add('placeholder'); this.style.display='none'; this.parentElement.innerHTML += 'Spreadshirt';"> </div>
                <div class="reference-logo"> <img src="/images/logos/ico-cert.png" alt="ICO Logo" onerror="this.onerror=null; this.src='https://placehold.co/150x50/eeeeee/aaaaaa?text=ICO'; this.parentElement.classList.add('placeholder'); this.style.display='none'; this.parentElement.innerHTML += 'ICO';"> </div>
                <div class="reference-logo"> <img src="/images/logos/zalando.png" alt="Zalando Logo" onerror="this.onerror=null; this.src='https://placehold.co/150x50/eeeeee/aaaaaa?text=Zalando'; this.parentElement.classList.add('placeholder'); this.style.display='none'; this.parentElement.innerHTML += 'Zalando';"> </div>
                 <div class="reference-logo"> <img src="/images/logos/concardis.png" alt="Concardis Logo" onerror="this.onerror=null; this.src='https://placehold.co/150x50/eeeeee/aaaaaa?text=Concardis'; this.parentElement.classList.add('placeholder'); this.style.display='none'; this.parentElement.innerHTML += 'Concardis';"> </div>
                 <div class="reference-logo"> <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/5/5f/Siemens-logo.svg/200px-Siemens-logo.svg.png" alt="Siemens Logo" onerror="this.onerror=null; this.src='https://placehold.co/150x50/eeeeee/aaaaaa?text=Siemens'; this.parentElement.classList.add('placeholder'); this.style.display='none'; this.parentElement.innerHTML += 'Siemens';"> </div>
                 <div class="reference-logo"> <img src="/images/logos/zonzoo.png" alt="Zonzoo Logo" onerror="this.onerror=null; this.src='https://placehold.co/150x50/eeeeee/aaaaaa?text=Zonzoo'; this.parentElement.classList.add('placeholder'); this.style.display='none'; this.parentElement.innerHTML += 'Zonzoo';"> </div>
                <div class="reference-logo"> <img src="/images/logos/olivettti.png" alt="Olivetti Logo" onerror="this.onerror=null; this.src='https://placehold.co/150x50/eeeeee/aaaaaa?text=Olivetti'; this.parentElement.classList.add('placeholder'); this.style.display='none'; this.parentElement.innerHTML += 'Olivetti';"> </div>
                 </div>
        </div>
    </section>

    <section id="get-started" class="py-10 md:py-14 lg:py-16 bg-gradient-to-r from-blue-600 to-purple-600 text-white px-4 sm:px-6 text-center fade-in-element">
        <div class="container mx-auto max-w-3xl">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold mb-4"><?= t('contactTitle') ?></h2>
            <p class="text-base md:text-lg lg:text-xl mb-8"><?= t('contactSubtitle') ?></p>

            <form id="contact-form" action="/submit_contact.php" method="POST" class="text-left space-y-5">
                 <div> <label for="name" class="block text-sm font-medium mb-1"><?= t('contactNameLabel') ?></label> <input type="text" id="name" name="name" required class="form-input"> </div>
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-5"> <div> <label for="email" class="block text-sm font-medium mb-1"><?= t('contactEmailLabel') ?></label> <input type="email" id="email" name="email" required class="form-input"> </div> <div> <label for="phone" class="block text-sm font-medium mb-1"><?= t('contactPhoneLabel') ?></label> <input type="tel" id="phone" name="phone" class="form-input"> </div> </div>
                 <div> <label for="message" class="block text-sm font-medium mb-1"><?= t('contactMessageLabel') ?></label> <textarea id="message" name="message" rows="5" required class="form-input form-textarea"></textarea> </div>
                 <div class="flex items-center space-x-2"> <input type="checkbox" id="send-copy" name="send-copy" class="form-checkbox h-4 w-4 rounded"> <label for="send-copy" class="text-sm"><?= t('contactSendCopyLabel') ?></label> </div>
                 <div class="text-center pt-4"> <button type="submit" class="bg-white hover:bg-gray-100 text-blue-600 font-semibold px-8 py-3 rounded-lg transition duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1 text-base md:text-lg"> <?= t('contactSubmitButton') ?> </button> </div>
            </form>
            <div id="form-status" class="mt-6 text-center text-lg font-medium hidden"></div>
            </div>
    </section>

    <section id="impressum" class="py-10 md:py-14 bg-gray-900 px-4 sm:px-6 text-gray-400 text-sm">
        <div class="container mx-auto max-w-4xl">
            <h2 class="text-xl sm:text-2xl font-semibold text-center mb-8 text-gray-200"><?= t('impressumTitle') ?></h2>
            <div class="mb-6"> <h3 class="text-lg font-semibold mb-2 text-gray-300"><?= t('impressumHeading1') ?></h3> <p>Jozef Kapicak IT Consulting</p> <p>Kurparkstr. 45</p> <p>81375 München</p> <p><?= t('impressumCountry') ?></p> </div>
            <div class="mb-6"> <h3 class="text-lg font-semibold mb-2 text-gray-300"><?= t('impressumHeading2') ?></h3> <p>Jozef Kapicak</p> </div>
            <div class="mb-6"> <h3 class="text-lg font-semibold mb-2 text-gray-300"><?= t('impressumHeading3') ?></h3> <p><?= t('impressumPhone') ?> +49-151 52562777</p> <p>E-Mail: info@sortia.ai</p> </div>
            <div class="mb-6"> <h3 class="text-lg font-semibold mb-2 text-gray-300"><?= t('impressumHeading4') ?></h3> <p><?= t('impressumVatText') ?></p> <p>DE280861639</p> </div>
            <div class="mb-6"> <h3 class="text-lg font-semibold mb-2 text-gray-300"><?= t('impressumHeading5') ?></h3> <p>Jozef Kapicak</p> <p>Kurparkstr. 45</p> <p>81375 München</p> </div>
            <div class="mb-6"> <h3 class="text-lg font-semibold mb-2 text-gray-300"><?= t('impressumDisclaimerTitle') ?></h3> <p><strong><?= t('impressumDisclaimerContentTitle') ?></strong> <span><?= t('impressumDisclaimerContentText') ?></span></p> <p><strong><?= t('impressumDisclaimerLinksTitle') ?></strong> <span><?= t('impressumDisclaimerLinksText') ?></span></p> </div>
             <div class="mb-6"> <h3 class="text-lg font-semibold mb-2 text-gray-300"><?= t('impressumCopyrightTitle') ?></h3> <p><?= t('impressumCopyrightText') ?></p> </div>
             <div class="mb-6"> <h3 class="text-lg font-semibold mb-2 text-gray-300"><?= t('impressumPrivacyTitle') ?></h3> <?php $privacyTextToRemoveDE = "[Optional: Link zur ausführlichen Datenschutzerklärung hinzufügen]"; $privacyTextToRemoveEN = "[Optional: Add link to full privacy policy]"; $fullPrivacyText = t('impressumPrivacyText'); $cleanedPrivacyText = str_replace($privacyTextToRemoveDE, "", $fullPrivacyText); $cleanedPrivacyText = str_replace($privacyTextToRemoveEN, "", $cleanedPrivacyText); ?> <p><?= trim($cleanedPrivacyText) ?></p> </div>
        </div>
    </section>

    <footer class="bg-gray-900 py-6 md:py-8 px-4 sm:px-6">
        <div class="container mx-auto text-center text-gray-500 text-sm md:text-base">
            <a href="#impressum" class="hover:text-gray-300 transition duration-300 mr-4"><?= t('footerImpressum') ?></a>
            <span class="mr-4">|</span>
            <p class="inline-block">&copy; <script>document.write(new Date().getFullYear())</script> Sortia. <?= t('footerCopyright') ?></p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // --- Mobile Menu Toggle ---
            const menuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');
            if(menuButton && mobileMenu) {
                menuButton.addEventListener('click', () => { mobileMenu.classList.toggle('hidden'); });
                const mobileLinks = mobileMenu.querySelectorAll('a');
                mobileLinks.forEach(link => { link.addEventListener('click', (e) => { if (link.getAttribute('href').startsWith('#')) { mobileMenu.classList.add('hidden'); } }); });
            }

            // --- Contact Form Submission Handling (AJAX) ---
            const contactForm = document.getElementById('contact-form');
            const formStatus = document.getElementById('form-status');
            if(contactForm && formStatus) {
                contactForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    formStatus.classList.remove('hidden', 'text-red-400', 'text-green-400', 'text-yellow-400');
                    formStatus.textContent = 'Sending...';
                    formStatus.classList.add('text-yellow-400');
                    const formData = new FormData(contactForm);
                    fetch('/submit_contact.php', { method: 'POST', body: formData })
                    .then(response => {
                        if (!response.ok) { return response.text().then(text => { throw new Error(`HTTP error! Status: ${response.status}. Response: ${text.substring(0, 200)}...`); }); }
                        const contentType = response.headers.get("content-type");
                        if (contentType && contentType.indexOf("application/json") !== -1) { return response.json(); }
                        else { return response.text().then(text => { throw new Error(`Unexpected response type: ${contentType}. Content: ${text.substring(0,200)}...`); }); }
                    })
                    .then(data => {
                        if (data.status === 'success') {
                            formStatus.textContent = data.message;
                            formStatus.classList.remove('text-yellow-400', 'text-red-400'); formStatus.classList.add('text-green-400');
                            contactForm.reset();
                        } else {
                            formStatus.textContent = data.message || 'An error occurred. Please try again.';
                            formStatus.classList.remove('text-yellow-400', 'text-green-400'); formStatus.classList.add('text-red-400');
                        }
                    })
                    .catch(error => {
                        console.error('Error submitting contact form:', error);
                        formStatus.textContent = error.message || 'A network error occurred. Please try again later.';
                        formStatus.classList.remove('text-yellow-400', 'text-green-400'); formStatus.classList.add('text-red-400');
                    })
                    .finally(() => { formStatus.classList.remove('hidden'); });
                });
            }

        }); // End DOMContentLoaded
    </script>

</body>
</html>
