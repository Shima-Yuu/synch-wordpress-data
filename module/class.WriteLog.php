<?php

/**
 * ログの処理
 * １日毎に新規のファイルに記述、1ヶ月毎にファイル削除
 * 
 * @param string $file_prefix ファイルを判別する接頭辞
 */

class WriteLog
{
  /** @var float 識別ID */
  private $uq_id;

  /** @var string ファイルを判別する接頭辞 */
  public $file_prefix;

  /**
   * 初期実行
   * @param string $file_prefix ファイルの接頭辞
   */
  public function __construct($file_prefix)
  {
    $this->file_prefix = $file_prefix;
    $this->uq_id       = microtime(true);

    createDirIfNotExists(SYNC_DATA_LOGS_PATH);
  }

  /** @var string ログディレクトリまでのパス */
  const LOCK_PATH = SYNC_DATA_LOGS_PATH . '/';

  /**
   * 日別ログファイル書き込み
   * @param string $prefix ファイル名の接頭辞
   * @param string $data   ファイル内のコンテンツ内容
   */
  public function write($prefix, $data)
  {
    $file_path = $prefix . '.day' . date('d') . '.log'; // 現在の日付を2桁の数字で付与

    // ファイルが存在し、かつ最終変更日時が本日の日付と一致する場合
    $should_append = file_exists($file_path) && date("Ymd") == date("Ymd", filemtime($file_path));

    // FILE_APPENDを動的に付与
    file_put_contents($file_path, $data . "\n", LOCK_EX | ($should_append ? FILE_APPEND : 0));
  }

  /**
   * ロックファイルの作成
   */
  public function lock()
  {
    $prefix = self::LOCK_PATH . $this->file_prefix . '_';
    $ft = '';
    while (file_exists(self::LOCK_PATH . $this->file_prefix . '.lock')) { // ロックファイルがある限りループさせる
      $ft = filemtime(self::LOCK_PATH . $this->file_prefix . '.lock'); // 最終更新日時を取得
      $ct = time(); // 現在の時間
      if ($ct - $ft > 30) {
        $this->unlock();
        $this->write($prefix, date('Y-m-d_H:i:s') . '	ロックファイルが残留していたので削除しました。(' . date('Y-m-d_H:i:s', $ft) . ')');
        break;
      }
      usleep(500000);
    }
    touch(self::LOCK_PATH . $this->file_prefix . '.lock');
    $this->write($prefix . '.lock', date('Y-m-d_H:i:s') . ' ' . $this->uq_id . ' create');
  }

  /**
   * ロックファイルの削除
   */
  public function unlock()
  {
    $prefix = self::LOCK_PATH . $this->file_prefix . '_';

    // ロックファイルが存在すれば削除、なければログを記録
    if (file_exists(self::LOCK_PATH . $this->file_prefix . '.lock')) {
      unlink(self::LOCK_PATH . $this->file_prefix . '.lock');
      $this->write($prefix . '.lock', date('Y-m-d_H:i:s') . ' ' . $this->uq_id . ' unlink');
    } else {
      $this->write($prefix . '.lock', date('Y-m-d_H:i:s') . ' ' . $this->uq_id . ' unlink_failed');
      $this->write($prefix, date('Y-m-d_H:i:s') . '	ロックファイルが見つかりませんでした。');
    }
  }
}
