<?php

require_once(SYNC_DATA_PATH . 'module/class.ControlMediaLibrary.php');
require_once(SYNC_DATA_PATH . 'module/class.UpdateTerms.php');
require_once(SYNC_DATA_PATH . 'module/class.UpdatePosts.php');

/**
 * データのインポート
 */
class ImportData
{
  public function __construct()
  {
    $this->importMediaLibrary();
    $this->importTax();
    $this->importPosts();
    $this->setFrontPage();
  }

  /**
   * メディアをインポートする
   */
  private function importMediaLibrary()
  {
    // 既存のメディアライブラリの削除
    ControlMediaLibrary::deleteMediaLibrary();

    // ~/(テーマ名)/data/media/配下のファイル群を ~/uploads/配下にコピー
    ControlMediaLibrary::uploadTargetDirectory(SYNC_DATA_MEDIA_PATH, SYNC_DATA_UPLOAD);

    // メディアライブラリの追加
    ControlMediaLibrary::addMediaLibrary();
  }

  /**
   * ターム情報をインポートする
   */
  private function importTax()
  {
    $import_tax = new UpdateTerms(SYNC_DATA_TAX_JSON_PATH);
  }

  /**
   * 投稿情報をインポートする
   */
  private function importPosts()
  {
    $import_posts = new UpdatePosts(SYNC_DATA_POSTS_JSON_PATH);
  }

  /**
   * Wordpressの表示設定をtopページに設定する
   */
  private function setFrontPage()
  {
    $top_page_obj = get_page_by_path('top');
    if (!empty($top_page_obj)) {
      $top_page_id = $top_page_obj->ID;
      update_option('page_on_front', $top_page_id);
      update_option('show_on_front', 'page');
    }
  }
}
