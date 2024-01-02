<?php

require_once(SYNC_DATA_PATH . 'module/class.ControlMediaLibrary.php');
require_once(SYNC_DATA_PATH . 'module/class.OutputWordpressHtml.php');
require_once(SYNC_DATA_PATH . 'module/class.UpdateJson.php');

/**
 * データのエクスポート
 */
class ExportData
{
  public function __construct()
  {
    $this->exportMediaLibrary();
    $this->exportTax();
    $this->exportPosts();
  }

  /**
   * メディアをエクスポートする
   */
  private function exportMediaLibrary()
  {
    // メディアJSONの更新
    UpdateJson::updateMediaJson();

    // ~/uploads/配下のファイル群を~/(テーマ名)/data/media/配下にコピー
    ControlMediaLibrary::uploadTargetDirectory(SYNC_DATA_UPLOAD, SYNC_DATA_MEDIA_PATH);
  }

  /**
   * ターム情報をエクスポートする
   */
  private function exportTax()
  {
    UpdateJson::updateTermJson();
  }

  /**
   * 投稿情報をエクスポートする
   */
  private function exportPosts()
  {
    UpdateJson::updatePostJson();
    OutputWordpressHtml::execThis();
  }
}
