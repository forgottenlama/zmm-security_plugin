<?php
/*
Plugin Name: ZMM Security
Description: Plugin zabezpečujúci vytváranie a overovanie používateľov na základe Grid Kariet. Vytvorený pre www.zmm.sk. 
Version: 1.2
Author: Branislav Dubravka
*/

// Vytvorenie submenu v admin dashboarde 
function grid_auth_admin_menu() {
    add_menu_page('Grid Kards', 'Grid Karty', 'manage_options', 'grid_auth', 'grid_auth_admin_page');  //submenu sa musí volať rovnako ako prvá subpage :(
    add_submenu_page('grid_auth', 'Vygenerované Karty', 'Vygenerované Karty', 'manage_options', 'grid_auth_list', 'grid_auth_list_page');
}
add_action('admin_menu', 'grid_auth_admin_menu');

// Oveerenie aby načítanie CSS a JS len na správnych a konkrétnych stránkach - nech to nenačíta nikde inde (aby sa to nerozbilo)
function grid_auth_enqueue_assets($hook) {
    echo '<script>console.log("Current hook: ' . $hook . '");</script>'; //vypisovanie do konzole na akej stránke sa nachádzame - for debugging

    if ($hook !== 'toplevel_page_grid_auth' && $hook !== 'grid-karty_page_grid_auth_list') {
        return;
    }    
    wp_enqueue_style('grid-auth-style', plugin_dir_url(__FILE__) . 'style.css');

    if ($hook == 'toplevel_page_grid_auth'){
        wp_enqueue_script('grid-auth-script', plugin_dir_url(__FILE__) . 'generate-grid.js', array('jquery'), null, true);
    }
    if ($hook == 'grid-karty_page_grid_auth_list'){
        wp_enqueue_script('grid-auth-script', plugin_dir_url(__FILE__) . 'delete-export.js', array('jquery'), null, true);
    }

    // Preklad premenných do JS + zabezpečenie nonce pre AJAX volania ()
    wp_localize_script('grid-auth-script', 'gridAuthData', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('generate_grid_nonce'),
        'delete_nonce' => wp_create_nonce('delete_grid_nonce')
    ]);
}
add_action('admin_enqueue_scripts', 'grid_auth_enqueue_assets');


// Podstránka na generovanie Grid kariet
function grid_auth_admin_page(){
    ?>
    <div class="wrap">
        <h1>Generovanie Grid kariet</h1>
        <label for="grid_secret">Vložte kľúč</label>
        <input type="text" id="grid_secret" required>
        <button id="generate_grid">Vygeneruj</button>
        <div id="get_result"></div>
    </div>
    <?php
}

// Funkcia na generovanie Grid kariet - deterministicky na základe zadaného kľúča 
// (vždy vygeneruje rovnakú kartu pre rovnaký kľúč - netreba ochrana proti opakovanému generovaniu)
function generate_deterministic_grid($key){
    $hash = hash('sha256', $key); // hash je vždy rovnaký pre daný kľúč
    $grid = [];

    for ($i = 0; $i < 36; $i++){
        $offset = $i * 2;
        if ($offset + 2 >= strlen($hash)){
            $hash .= hash('sha256', $hash);
        }
        $hexPair = substr($hash, $offset, 2);
        $digit = hexdec($hexPair[0]) % 10;
        $letter = chr(65 + (hexdec($hexPair[1]) % 26));
        $row = floor($i / 6) + 1;
        $col = ($i % 6) + 1;
        $grid["{$row}_{$col}"] = $digit . $letter;
    }
    return $grid;
}

// Uloženie Grid karty do súboru
// ochrana pomocou nonce
// AJAX volanie z generate-grid.js
function save_and_generate_grid() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'generate_grid_nonce')) {
        wp_send_json_error("Neplatný nonce.");
        wp_die();
    }

    $secret = sanitize_text_field($_POST['grid_secret']); // grid_secret je input zo zadávania kľúča
    if (empty($secret)) {
        wp_send_json_error("Tajný kľúč nemôže byť prázdny.");
        wp_die();
    }

    $grid = generate_deterministic_grid($secret);
    $grid_json = json_encode($grid, JSON_PRETTY_PRINT);

    $upload_dir = plugin_dir_path(__FILE__) . 'grid-cards/';

    // Kontrola, či priečinok existuje, a ak nie, vytvoríme ho
    if (!file_exists($upload_dir) && !mkdir($upload_dir, 0755, true)) {
        wp_send_json_error("Nepodarilo sa vytvoriť priečinok pre karty.");
        wp_die();
    }

    $file_path = $upload_dir . "grid-{$secret}.json";
    
    if (file_put_contents($file_path, $grid_json) === false) {
        wp_send_json_error("Nepodarilo sa uložiť kartu.");
        wp_die();
    }

    wp_send_json_success("Karta bola úspešne uložená.");
}

add_action('wp_ajax_save_and_generate_grid', 'save_and_generate_grid');

// Podstránka na zobrazenie uložených kariet
function grid_auth_list_page() {
    $upload_dir = plugin_dir_path(__FILE__) . 'grid-cards/';
    $files = glob($upload_dir . "grid-*.json");

    echo '<div class="wrap"><h1>Vygenerované Grid Karty</h1>';

    if (!$files) {
        echo '<p>Žiadne karty neboli vygenerované.</p>';
    } else {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th id="table-head">Kľúč</th><th id="table-head">Grid</th><th id="table-head">Akcie</th></tr></thead><tbody>';
        
        foreach ($files as $file) { //pre každú kartu
            $key = basename($file, ".json");
            $key = str_replace("grid-", "", $key);
            $grid = json_decode(file_get_contents($file), true);
    
            echo '<tr>';
            echo '<td id="table-cell">' . esc_html($key) . '</td>';

            // Zobrazenie karty ako mriežky
            echo '<td id="grid-cell">';
            echo '<div class="grid-container">';  // wrapper pre mriežku
            foreach ($grid as $position => $value) {
                echo '<div class="grid-item">' . esc_html($value) . '</div>';
            }
            echo '</div>';
            echo '</td>';

            echo '<td id="button-cell"><button class="delete-grid" data-key="' . esc_attr($key) . '">Odstrániť</button>
                <button class="export-grid" data-key="' . esc_attr($key) . '">Exportovať</button></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    echo '</div>';
}

// Odstránenie Grid karty
function delete_grid_card() {
    check_ajax_referer('delete_grid_nonce', 'nonce'); // Overenie nonce

    if (!isset($_POST['grid_key'])) {
        wp_send_json_error("Nebolo zadané ID karty.");
    }

    $key = sanitize_text_field($_POST['grid_key']);
    $file_path = plugin_dir_path(__FILE__) . "grid-cards/grid-{$key}.json";

    if (file_exists($file_path)) {
        unlink($file_path);      // Odstránenie súboru
        wp_send_json_success("Karta bola úspešne odstránená.");
    } else {
        wp_send_json_error("Karta neexistuje.");
    }
}
add_action('wp_ajax_delete_grid_card', 'delete_grid_card');

// Konverzia do spravneho kodovania textu pre PDF
function cp1250($text) {
    return iconv('UTF-8', 'CP1250//TRANSLIT', $text);
}

// Export Grid karty do PDF
function export_grid_to_pdf() {
    if (!isset($_GET['grid_key'])) {
        wp_die('Chýba grid_key.');
    }
    $grid_key = sanitize_text_field($_GET['grid_key']);
    $file_path = plugin_dir_path(__FILE__) . "grid-cards/grid-{$grid_key}.json";

    if (!file_exists($file_path)) {
        wp_die('Grid karta neexistuje. $grid_key: ' . $grid_key . " grid-cards/grid-{$grid_key}.json");
    }

    $grid_data = json_decode(file_get_contents($file_path), true);
    if (!$grid_data) {
        wp_die('Nepodarilo sa načítať grid kartu.');
    }
    error_log("data loaded"); // fordebugging

    // Načítanie knižnice FPDF (umiestnená v priečinku pluginu)
    require_once plugin_dir_path(__FILE__) . 'fpdf/fpdf.php';
    error_log("FPDF loaded"); // for debugging
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->AddFont('NotoSans-Regular', '', 'NotoSans-Regular.php');
    $pdf->SetFont('NotoSans-Regular', '', 16);
    $pdf->Cell(0, 10, 'Grid karta pre www.zmm.sk', 0, 1, 'C');
    $pdf->Cell(0, 10, '-----------------------------------', 0, 1, 'C');
    $pdf->Cell(0, 10, cp1250($grid_key), 0, 1, 'C');
    $pdf->Ln(5);

    $cols = 6;
    $cell_width = 30;
    $cell_height = 10;

    // Generovanie karty z JSON do PDF
    for ($r = 1; $r <= 6; $r++) {
        for ($c = 1; $c <= 6; $c++) {
            $key = "{$r}_{$c}";
            $value = isset($grid_data[$key]) ? $grid_data[$key] : '';
            $pdf->Cell($cell_width, $cell_height, $value, 1, 0, 'C');
        }
        $pdf->Ln();
    }

    // dátum a podpisová poznámka
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 10, 'Generated: ' . date('d.m.Y H:i') . ' by zmm.sk', 0, 1, 'R');
    error_log("pdf generated"); // for debugging

    $pdf->Output('D', 'grid_card_' . cp1250($grid_key) . '.pdf');  // Stiahnutie PDF
    exit;

}
add_action('wp_ajax_export_grid_to_pdf', 'export_grid_to_pdf');


//
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
//tu je overovanie užívateľov
function verify_grid_card($user_input_1, $user_input_2, $coord_1, $coord_2) {
    $upload_dir = plugin_dir_path(__FILE__) . 'grid-cards/';
    $files = glob($upload_dir . "grid-*.json");

    // Prechádzame všetky súbory a hľadáme zhodu
    foreach ($files as $file) {
        $grid = json_decode(file_get_contents($file), true);

    // Kontrola zhody užívateľského vstupu na daných súradniciach
        $field_1 = isset($grid[$coord_1]) ? $grid[$coord_1] : null;
        $field_2 = isset($grid[$coord_2]) ? $grid[$coord_2] : null;

        // Kontrola či políčka nie sú null (či súradnice sú platné)
        if ($field_1 === null || $field_2 === null) {
            continue; // skok na ďalší súbor
        }

    // odstránime medzery a porovnáme s užívateľovým vstupom - nezaleží či sú pismená veľké alebo malé
        $user_input_1 = strtolower(trim($user_input_1));
        $user_input_2 = strtolower(trim($user_input_2));
        $field_1 = strtolower(trim($field_1));
        $field_2 = strtolower(trim($field_2));

        // Porovnanie s užívateľovým vstupom
        if (($user_input_1 == $field_1) && ($user_input_2 == $field_2)) {
            $user_key = basename($file, ".json");
            $user_key = str_replace("grid-", "", $user_key);
            return $user_key; 
        }
    }

    // Ak nebola nájdená žiadna zhodná karta
    return false;
}


// funkcia pre shortcode - tvorba overovacej stránky pre užívateľov
// [grid_auth_verify] - vložiť do stránky
function grid_auth_verify_shortcode() {
    $output = '';

// Skontrolujeme, či bol používateľ už overený
    if (isset($_COOKIE['zmm-user'])) {
        $user_name = htmlspecialchars($_COOKIE['zmm-user']);
        return "
        <p style='color: green; font-weight: bold;'>Overenie úspešné! Užívateľ: {$user_name}</p>
        <button id='logoutBtn' style='padding: 10px 15px; background-color:rgb(231, 25, 25); color: white; border: none; border-radius: 4px; cursor: pointer;'>Odhlásiť sa</button>
        <script>
        document.getElementById('logoutBtn').addEventListener('click', function() {
            document.cookie = 'zmm-user=; path=/; expires=' + new Date(Date.now() - 3600 * 1000).toUTCString(); // nastaví cookie na minulý čas
            location.reload(); // Obnoví stránku
        });

        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        </script>
        ";

        // Ak bol užívateľ overený, neukáže sa ďalší formulár
    }

//overovací formulár
    // Skontroluj či používateľ už zadal údaje
    if (isset($_POST['grid_input_1']) && isset($_POST['grid_input_2']) && isset($_POST['coord_1']) && isset($_POST['coord_2'])) {
        $user_input_1 = sanitize_text_field($_POST['grid_input_1']);
        $user_input_2 = sanitize_text_field($_POST['grid_input_2']);
        $coord_1 = sanitize_text_field($_POST['coord_1']);
        $coord_2 = sanitize_text_field($_POST['coord_2']);
        
        // Overenie
        $user = verify_grid_card($user_input_1, $user_input_2, $coord_1, $coord_2);

        if ($user) {
            // Pomocou JS uložíme cookie že užívateľ bol overený
            // cookie prežije 1 hodinu
            // zároveň sa vytvorí stránka o úspešnom prihlásení + možnosť odhlásiť sa
            // je tu zábrana proti znovu odoslaniu formulára (obnovenie stránky)
            return "
            <script>
            function nastavCookie(name, value, durationSeconds) {
                var expires = '';
                if (durationSeconds) {
                    var date = new Date(Date.now() + durationSeconds * 1000);
                    expires = '; expires=' + date.toUTCString();
                }
                document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/';
            }
            nastavCookie('zmm-user', '{$user}', 3600);
            location.reload();

            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
            if (history.length > 3){
                history.go(-2)
            }
            </script>

            <p style='color: green; font-weight: bold;'>Overenie úspešné! Užívateľ: {$user}</p>
            <button id='logoutBtn' style='padding: 10px 15px; background-color:rgb(231, 25, 25); color: white; border: none; border-radius: 4px; cursor: pointer;'>Odhlásiť sa</button>
            <script>
            document.getElementById('logoutBtn').addEventListener('click', function() {
                document.cookie = 'user_verified=; path=/; expires=' + new Date(Date.now() - 3600 * 1000).toUTCString();
                location.reload(); // Obnoví stránku
                });
            </script>
            ";
        } else {
            // V prípade neúspešného prihlásenia
            $output .= "<p style='color: red; font-weight: bold;'>Chyba! Údaje neboli správne. Skúste to znovu.</p>";
        }
    }

//logika overenia
    // Získaj náhodné dve polia z Grid karty (len pre zobrazenie)
    $upload_dir = plugin_dir_path(__FILE__) . 'grid-cards/';
    $files = glob($upload_dir . "grid-*.json");
    if (count($files) > 0) {
        $grid = json_decode(file_get_contents($files[0]), true);

        // Náhodne vyberieme dve súradnice pre zobrazenie (používateľovi)
        $random_row_1 = rand(1, 6); 
        $random_col_1 = rand(1, 6); 
        $random_row_2 = rand(1, 6); 
        $random_col_2 = rand(1, 6); 
        
        $random_field_1 = $grid["{$random_row_1}_{$random_col_1}"];
        $random_field_2 = $grid["{$random_row_2}_{$random_col_2}"];

// Prihlašovací formulár
        // for debbuging:                 
            // <p style='display: none;'><strong>1. súradnice: {$random_row_1}_{$random_col_1} - Hodnota: {$random_field_1}</strong></p>
            // <p style='display: none;'><strong>2. súradnice: {$random_row_2}_{$random_col_2} - Hodnota: {$random_field_2}</strong></p>
        $output .= "
            <div style='max-width: 400px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; border: 1px solid #ddd; border-radius: 8px;'>
                <h2>Zadajte hodnoty pre tieto políčka z Grid karty</h2>

                <form method='POST' style='display: flex; flex-direction: column; gap: 15px;'>
                    <div>
                        <input type='text' name='coord_1' value='{$random_row_1}_{$random_col_1}' readonly required style='width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; display: none;' />
                    </div>
                    <div>
                        <label for='grid_input_1' style='font-weight: bold;'>Zadajte hodnotu pre súradnice: <em>riadok {$random_row_1}, stĺpec {$random_col_1}</em> </label>
                        <input type='text' name='grid_input_1' required style='width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;' />
                    </div>
                    <div>
                        <input type='text' name='coord_2' value='{$random_row_2}_{$random_col_2}' readonly required style='width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; display: none;' />
                    </div>
                    <div>
                        <label for='grid_input_2' style='font-weight: bold;'>Zadajte hodnotu pre súradnice: <em>riadok {$random_row_2}, stĺpec {$random_col_2}</em></label>
                        <input type='text' name='grid_input_2' required style='width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;' />
                    </div>
                    <button type='submit' style='padding: 10px 15px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;'>Overiť</button>
                </form>
            </div>
            <script>
                if (window.history.replaceState) {
                    window.history.replaceState(null, null, window.location.href);
                }
            </script>
        "; // zábrana znovu odoslania formulára pri obnovení stránky
    } else {
        $output .= "<p>Žiadna Grid karta nie je k dispozícii.</p>";
    }

    return $output;
}
add_shortcode('grid_auth_verify', 'grid_auth_verify_shortcode');


// funkcia pre shortcode - zabezpečí stráku iba pre overených používateľov
// [protected_page] - vložiť do stránky pre ochranu
function protected_page_shortcode() {
    // Skontrolujeme, či existuje cookie s overeným užívateľom
    if (!isset($_COOKIE['zmm-user'])) {
        // Adresa stránky s prihlasovaním je hardcoded - zmeniť podľa potreby
        // Ak cookie neexistuje, presmerujeme na prihlasovaciu stránku, timeout 1 sekunda
        return "
        <script>
            location.reload();
            document.body.innerHTML = '<h2 style=\"text-align: center; color: red;\">Prístup zamietnutý. Presmerovávam na prihlasovaciu stránku</h2>';
            setTimeout(function() {
                window.location.href = 'https://zmm.sk/prihlasenie'; // URL na prihlasenie
            }, 1000);
        </script>";
    }


    return ""; // Ak je používateľ overený, stránka sa normálne zobrazí
}
add_shortcode('protected_page', 'protected_page_shortcode');


?>