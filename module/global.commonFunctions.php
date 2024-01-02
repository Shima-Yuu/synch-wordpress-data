<?php

/**
 * 汎用的な関数を定義
 */


/**
 * 指定したディレクトリが存在しない場合、ディレクトリを作成する
 * @param string $dir_path 指定するディレクトリのパス
 */
function createDirIfNotExists($dir_path)
{
  if (!file_exists($dir_path)) {
    mkdir($dir_path);
  };
}

/**
 * 指定したディレクトリの削除
 * @param string $dir_path 指定するディレクトリのパス
 */
function removeDir($dir_path)
{
  if (!is_dir($dir_path)) {
    throw new Exception("Directory $dir_path does not exist.");
  }

  $dh = opendir($dir_path);
  if ($dh === false) {
    throw new Exception("Failed to open $dir_path");
  }

  while (($file = readdir($dh)) !== false) {
    if ($file === '.' || $file === '..') {
      continue;
    }

    $path = rtrim($dir_path, '/') . '/' . $file;

    if (is_dir($path)) {
      removeDir($path);
    } else {
      unlink($path);
    }
  }

  closedir($dh);
  rmdir($dir_path);
}

/**
 * ログの記録
 * @param string $dir_path 指定するディレクトリのパス
 */
function writeLogFunc($file_prefix, $file_content)
{
  $write_log = new WriteLog($file_prefix);
  $file_path = SYNC_DATA_LOGS_PATH . '/' . $write_log->file_prefix;
  $write_log->lock();
  $write_log->write($file_path, date('Y-m-d_H:i:s') . '	' . $file_content);
  $write_log->unlock();
}
