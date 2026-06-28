<?php

if (!class_exists('TaxoPress_Linked_Terms_Schema')) {
    /**
     * TaxoPress_Linked_Terms_Schema
     */
    class TaxoPress_Linked_Terms_Schema
    {
        /**
         * Linked Terms table name
         *
         * @return string
         */
        public static function tableName()
        {
            global $wpdb;

            return $wpdb->prefix . 'taxopress_linked_terms';
        }


        /**
         * Check if a table exists
         *
         * @param string $table_name
         * @return bool
         */
        public static function tableExists($table_name)
        {
            global $wpdb;

            return $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        }


        /**
         * Create linked terms table if not exist
         *
         * @return void
         */
        public static function createTableIfNotExists()
        {
            global $wpdb;

            $table_name = self::tableName();

            if (!self::tableExists($table_name)) {
                $charset_collate = $wpdb->get_charset_collate();

                $sql = "CREATE TABLE {$table_name} (
                    id bigint(20) unsigned NOT NULL auto_increment,
                    term_id bigint(20) unsigned NOT NULL,
                    linked_term_id bigint(20) unsigned NOT NULL,
                    term_name varchar(200) NOT NULL default '',
                    linked_term_name varchar(200) NOT NULL default '',
                    term_taxonomy varchar(200) NOT NULL default '',
                    linked_term_taxonomy varchar(200) NOT NULL default '',
                    meta_data longtext NOT NULL default '',
                    PRIMARY KEY  (id),
                    UNIQUE KEY unique_linked_term (term_id, linked_term_id),
                    KEY term_id (term_id),
                    KEY linked_term_id (linked_term_id)
                ) $charset_collate;";

                self::createTable($sql);
            }
        }

        /**
         * Create new table
         *
         * @param string $sql
         */
        private static function createTable($sql)
        {
            if (!function_exists('dbDelta')) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            }

            dbDelta($sql);
        }
    }
}