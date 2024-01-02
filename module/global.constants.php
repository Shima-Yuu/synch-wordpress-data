<?php

/**
 * 定数の定義
 */


/** @var string http://(ドメイン)/wp-content/plugins/synch-wordpress-data*/
define('SYNC_DATA_URL', plugin_dir_url(dirname(__FILE__)));

/** @var string (ローカルパス)/wp-content/themes/(テーマ名) */
define('SYNC_DATA_THEME', get_stylesheet_directory());

/** @var string (ローカルパス)/wp-content/uploads */
define('SYNC_DATA_UPLOAD', wp_upload_dir()['basedir']);


/** @var string ajax通信用のインポートのアクションフック */
define('ACTION_NAME_IMPORT', 'import_data');

/** @var string ajax通信用のエクスポートのアクションフック */
define('ACTION_NAME_EXPORT', 'export_data');


/** @var string (ローカルパス)/wp-content/themes/(テーマ名)/logs */
define('SYNC_DATA_LOGS_PATH', SYNC_DATA_THEME . '/logs');

/** @var string タームのログファイルの接頭辞 */
define('SYNC_DATA_TAX_LOG_PREFIX', 'tax');

/** @var string 投稿のログファイルの接頭辞 */
define('SYNC_DATA_POST_LOG_PREFIX', 'post');

/** @var string メディアのログファイルの接頭辞 */
define('SYNC_DATA_MEDIA_LOG_PREFIX', 'media');

/** @var string ACFのログファイルの接頭辞 */
define('SYNC_DATA_ACF_LOG_PREFIX', 'acf');


/** @var string jsonとメディアを管理するディレクトリの名前 */
define('SYNC_DATA_DATA_DIR_NAME', 'data');

/** @var string jsonを管理するディレクトリの名前 */
define('SYNC_DATA_JSON_DIR_NAME', 'json');

/** @var string メディアを管理するディレクトリの名前 */
define('SYNC_DATA_MEDIA_DIR_NAME', 'media');

/** @var string 投稿情報を管理するディレクトリの名前 */
define('SYNC_DATA_WP_DIR_NAME', 'WP');

/** @var string 固定ページの投稿情報を管理するディレクトリの名前 */
define('SYNC_DATA_PAGES_DIR_NAME', 'pages');

/** @var string カスタム投稿の投稿情報を管理するディレクトリの名前 */
define('SYNC_DATA_POST_TYPE_DIR_NAME', 'post_type');


/** @var string (ローカルパス)/wp-content/themes/(テーマ名)/data */
define('SYNC_DATA_DATA_PATH', SYNC_DATA_THEME . '/' . SYNC_DATA_DATA_DIR_NAME);

/** @var string (ローカルパス)/wp-content/themes/(テーマ名)/data/json */
define('SYNC_DATA_JSON_PATH', SYNC_DATA_THEME . '/' . SYNC_DATA_DATA_DIR_NAME . '/' . SYNC_DATA_JSON_DIR_NAME);

/** @var string (ローカルパス)/wp-content/themes/(テーマ名)/data/media */
define('SYNC_DATA_MEDIA_PATH', SYNC_DATA_THEME . '/' . SYNC_DATA_DATA_DIR_NAME . '/' . SYNC_DATA_MEDIA_DIR_NAME);

/** @var string (ローカルパス)/wp-content/themes/(テーマ名)/WP */
define('SYNC_DATA_WP_PATH', SYNC_DATA_THEME . '/'  . SYNC_DATA_WP_DIR_NAME);


/** @var string (ローカルパス)/wp-content/themes/(テーマ名)/data/json/tax.json */
define('SYNC_DATA_TAX_JSON_PATH', SYNC_DATA_JSON_PATH . '/tax.json');

/** @var string (ローカルパス)/wp-content/themes/(テーマ名)/data/json/posts.json */
define('SYNC_DATA_POSTS_JSON_PATH', SYNC_DATA_JSON_PATH . '/posts.json');

/** @var string (ローカルパス)/wp-content/themes/(テーマ名)/data/json/media.json */
define('SYNC_DATA_MEDIA_JSON_PATH', SYNC_DATA_JSON_PATH . '/media.json');
