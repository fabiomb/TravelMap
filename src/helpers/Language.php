<?php
/**
 * Language Helper
 * 
 * Sistema de internacionalización (i18n) para TravelMap
 * Carga y gestiona las traducciones desde archivos JSON
 */

class Language {
    private static $instance = null;
    private $currentLang = 'en';
    private $translations = [];
    private $availableLanguages = ['en', 'es'];
    private $langPath;
    
    /**
     * Constructor privado para patrón Singleton
     */
    private function __construct() {
        $this->langPath = dirname(dirname(__DIR__)) . '/lang';
        $this->detectLanguage();
        $this->loadTranslations();
    }
    
    /**
     * Obtiene la instancia única de la clase (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Detecta el idioma a utilizar basado en:
     * 1. Parámetro GET 'lang'
     * 2. Cookie 'travelmap_lang'
     * 3. Configuración del sistema (BD)
     * 4. Idioma del navegador
     * 5. Idioma por defecto (inglés)
     */
    private function detectLanguage() {
        // 1. Parámetro GET
        if (isset($_GET['lang']) && in_array($_GET['lang'], $this->availableLanguages)) {
            $this->currentLang = $_GET['lang'];
            // Guardar en cookie
            setcookie('travelmap_lang', $this->currentLang, time() + (365 * 24 * 60 * 60), '/');
            return;
        }
        
        // 2. Cookie
        if (isset($_COOKIE['travelmap_lang']) && in_array($_COOKIE['travelmap_lang'], $this->availableLanguages)) {
            $this->currentLang = $_COOKIE['travelmap_lang'];
            return;
        }
        
        // 3. Configuración del sistema (si existe)
        try {
            if (defined('ROOT_PATH')) {
                require_once ROOT_PATH . '/config/db.php';
                require_once ROOT_PATH . '/src/models/Settings.php';
                
                $db = getDB();
                $settings = new Settings($db);
                $defaultLang = $settings->get('default_language', 'en');
                
                if (in_array($defaultLang, $this->availableLanguages)) {
                    $this->currentLang = $defaultLang;
                    return;
                }
            }
        } catch (Exception $e) {
            // Si falla, continuar con otras opciones
        }
        
        // 4. Idioma del navegador
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
            if (in_array($browserLang, $this->availableLanguages)) {
                $this->currentLang = $browserLang;
                return;
            }
        }
        
        // 5. Idioma por defecto
        $this->currentLang = 'en';
    }
    
    /**
     * Carga las traducciones del idioma actual
     */
    private function loadTranslations() {
        $filePath = $this->langPath . '/' . $this->currentLang . '.json';
        
        if (file_exists($filePath)) {
            $json = file_get_contents($filePath);
            $this->translations = json_decode($json, true);
            
            if ($this->translations === null) {
                error_log("Error loading translations for language: {$this->currentLang}");
                $this->translations = [];
            }
        } else {
            error_log("Translation file not found: {$filePath}");
            $this->translations = [];
        }
    }
    
    /**
     * Obtiene una traducción por su clave
     * 
     * @param string $key Clave de la traducción (ej: "app.name" o "navigation.home")
     * @param string $default Valor por defecto si no se encuentra la traducción
     * @return string Texto traducido
     */
    public function get($key, $default = '') {
        $keys = explode('.', $key);
        $value = $this->translations;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default !== '' ? $default : $key;
            }
        }
        
        return is_string($value) ? $value : ($default !== '' ? $default : $key);
    }
    
    /**
     * Alias corto para get()
     */
    public function t($key, $default = '') {
        return $this->get($key, $default);
    }
    
    /**
     * Obtiene el idioma actual
     */
    public function getCurrentLanguage() {
        return $this->currentLang;
    }
    
    /**
     * Establece el idioma actual
     */
    public function setLanguage($lang) {
        if (in_array($lang, $this->availableLanguages)) {
            $this->currentLang = $lang;
            $this->loadTranslations();
            setcookie('travelmap_lang', $lang, time() + (365 * 24 * 60 * 60), '/');
            return true;
        }
        return false;
    }
    
    /**
     * Obtiene todos los idiomas disponibles
     */
    public function getAvailableLanguages() {
        return $this->availableLanguages;
    }
    
    /**
     * Obtiene el nombre del idioma en su propio idioma
     */
    public function getLanguageName($langCode) {
        $names = [
            'en' => 'English',
            'es' => 'Español'
        ];
        return $names[$langCode] ?? $langCode;
    }
    
    /**
     * Obtiene todas las traducciones como JSON (para usar en JavaScript)
     */
    public function getTranslationsAsJson() {
        return json_encode($this->translations);
    }
}

/**
 * Función helper global para obtener traducciones
 * 
 * @param string $key Clave de la traducción
 * @param string $default Valor por defecto
 * @return string Texto traducido
 */
function __($key, $default = '') {
    return Language::getInstance()->get($key, $default);
}

/**
 * Función helper global para obtener el idioma actual
 */
function current_lang() {
    return Language::getInstance()->getCurrentLanguage();
}
