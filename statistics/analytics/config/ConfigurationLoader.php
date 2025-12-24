<?php
/**
 * Configuration Loader
 * Loads form type configurations from JSON file
 */

class ConfigurationLoader {
    private static $configCache = null;
    private static $configPath = __DIR__ . '/form_configs.json';

    /**
     * Load all configurations from JSON file
     * @return array
     */
    public static function loadAll() {
        if (self::$configCache === null) {
            if (!file_exists(self::$configPath)) {
                throw new RuntimeException("Configuration file not found: " . self::$configPath);
            }

            $jsonContent = file_get_contents(self::$configPath);
            self::$configCache = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException("Invalid JSON in configuration file: " . json_last_error_msg());
            }
        }

        return self::$configCache;
    }

    /**
     * Get configuration for a specific form type and evaluator type
     * @param string $formType
     * @param string $evaluatorType
     * @return array|null
     */
    public static function getConfig($formType, $evaluatorType) {
        $allConfigs = self::loadAll();

        if (!isset($allConfigs[$formType])) {
            return null;
        }

        if (!isset($allConfigs[$formType][$evaluatorType])) {
            return null;
        }

        return $allConfigs[$formType][$evaluatorType];
    }

    /**
     * Check if a form type uses custom implementation
     * @param string $formType
     * @param string $evaluatorType
     * @return bool
     */
    public static function usesCustomFile($formType, $evaluatorType) {
        $config = self::getConfig($formType, $evaluatorType);
        return $config && ($config['use_custom_file'] ?? false);
    }

    /**
     * Get custom file path if exists
     * @param string $formType
     * @param string $evaluatorType
     * @return string|null
     */
    public static function getCustomFilePath($formType, $evaluatorType) {
        $config = self::getConfig($formType, $evaluatorType);
        
        if ($config && ($config['use_custom_file'] ?? false)) {
            return __DIR__ . '/../../' . ($config['custom_path'] ?? '');
        }

        return null;
    }

    /**
     * Clear configuration cache (useful for testing)
     */
    public static function clearCache() {
        self::$configCache = null;
    }
}
