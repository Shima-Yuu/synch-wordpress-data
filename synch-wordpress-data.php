<?php
/*
  Plugin Name: Synch Wordpress Data
  Description: Wordpress内のデータを同期させる。
  Author: shimazaki
*/


/**
 * (ローカルパス)/wp-content/plugins/synch-wordpress-data
 * @var string path
 */
define('SYNC_DATA_PATH', plugin_dir_path(__FILE__));

// モジュールの読み込み
require_once(SYNC_DATA_PATH . 'module/global.constants.php');       // 定数の読み込み
require_once(SYNC_DATA_PATH . 'module/global.commonFunctions.php'); // 汎用的な関数の読み込み
require_once(SYNC_DATA_PATH . 'module/class.WriteLog.php');         // ログファイルの生成
require_once(SYNC_DATA_PATH . 'module/class.ExportData.php');       // エクスポート時の処理
require_once(SYNC_DATA_PATH . 'module/class.ImportData.php');       // インポート時の処理

// init時、自身のクラスを呼び出す
add_action('init', ['SynchWordpressData', 'getSelf']);

class SynchWordpressData
{
  /**
   * 自身のクラスを呼び出す
   */
  public static function getSelf()
  {
    return new self();
  }

  /**
   * 初期実行
   */
  public function __construct()
  {
    if (is_admin() && is_user_logged_in()) {

      // 管理画面系
      add_action('admin_menu', [$this, 'setPluginMenu']);
      add_action('admin_enqueue_scripts', [$this, 'loadResourceAdminPage']);

      // ajax通信
      add_action('wp_ajax_' . ACTION_NAME_IMPORT . '', [$this, 'importData']);
      add_action('wp_ajax_nopriv_' . ACTION_NAME_IMPORT . '', [$this, 'importData']);

      add_action('wp_ajax_' . ACTION_NAME_EXPORT . '', [$this, 'exportData']);
      add_action('wp_ajax_nopriv_' . ACTION_NAME_EXPORT . '', [$this, 'exportData']);
    }

    /** @see https://developer.wordpress.org/reference/hooks/the_content/ */
    add_filter('the_content', [$this, 'updateWordpressHtml']);
  }

  /**
   * 管理画面のメニューに表示
   */
  public function setPluginMenu()
  {
    add_menu_page(
      'Sync Data',
      'Sync Data',
      'manage_options',
      'synch_wordpress_data',
      [$this, 'dashboardPage'],
      'dashicons-admin-tools',
      1
    );
  }

  /**
   * 管理画面ログイン時、下記リソースの読み込み
   */
  public function loadResourceAdminPage()
  {
    wp_enqueue_style('sync_data_style', SYNC_DATA_URL . 'views/assets/css/index.css');
    wp_enqueue_script('sync_data_jquery', SYNC_DATA_URL . 'views/assets/js/jquery-3.6.0.min.js');
  }

  /**
   * プラグインページのHTMLを定義
   */
  public function dashboardPage()
  {
    include_once(SYNC_DATA_PATH . 'views/dashboard-content.php');
  }

  /**
   * インポート処理
   */
  public function importData()
  {
    $this->createDirs();
    $import_data = new ImportData();
  }

  /**
   * エクスポート処理
   */
  public function exportData()
  {
    $this->createDirs();
    $export_data = new ExportData();
  }

  /**
   * ディレクトリが存在しない場合に、作成する
   */
  private function createDirs()
  {
    createDirIfNotExists(SYNC_DATA_WP_PATH);
    createDirIfNotExists(SYNC_DATA_DATA_PATH);
    createDirIfNotExists(SYNC_DATA_JSON_PATH);
    createDirIfNotExists(SYNC_DATA_MEDIA_PATH);
  }

  /**
   * ~/WP/配下のHTMLを読み込ませるように変更 & DBの更新
   * @param  string $content Wordpressの投稿のHTML
   * @return string WordpressのHTML
   */
  public function updateWordpressHtml($content)
  {
    global $post;

    // 現在のページのルートパスを取得
    $current_page_root_path = parse_url(home_url($_SERVER['REQUEST_URI']));
    $current_page_root_path = isset($current_page_root_path['path']) ? $current_page_root_path['path'] : '';

    // 末尾が「/」の場合は削除
    if (!empty($current_page_root_path)) {
      $current_page_root_path = substr($current_page_root_path, -1) === '/' ? substr($current_page_root_path, 0, -1) : $current_page_root_path;
    }

    // 固定ページ・カスタム投稿タイプでファイルパスを書き換える
    $post_type = get_post_type();
    if (is_single() && $post_type !== 'post' && $post_type !== 'attachment') { // カスタム投稿の場合
      $file_path = SYNC_DATA_WP_PATH . '/' . SYNC_DATA_POST_TYPE_DIR_NAME . $current_page_root_path . '.html';
    } else {
      $file_path = SYNC_DATA_WP_PATH . '/' . SYNC_DATA_PAGES_DIR_NAME . $current_page_root_path . '.html';
    }

    if (file_exists($file_path)) {
      // ~/WP/配下のHTMLを読み込む
      $content = mb_convert_encoding(file_get_contents($file_path), 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN');

      // DB更新
      if ($post) {
        $updated_post = array(
          'ID'           => $post->ID,
          'post_content' => mb_convert_encoding(file_get_contents($file_path), 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN'),
        );

        /** @see https://developer.wordpress.org/reference/functions/wp_update_post/ */
        wp_update_post($updated_post);
      }
    }

    return $content;
  }
}
